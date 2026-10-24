# Technical study: Droopy, an AI guide for a Drupal portfolio

**Status:** in production code on `main` since 2026-10-19 · **Last verified against the code:** 2026-10-23
**Design spec:** [`docs/specs/2026-10-18-droopy-ai-guide-design.md`](../specs/2026-10-18-droopy-ai-guide-design.md)

## 1. Summary

Droopy is a floating mascot on every public page of this Drupal 11 site that answers visitors'
questions about the site owner. A custom JSON endpoint sends each question, plus a system prompt
built from the profile node, to a free-tier LLM. It falls back from Groq to Gemini and answers with
a friendly "napping" message when both fail.

| Aspect | What we built |
|---|---|
| Platform | Drupal 11.3, PHP 8.3, one custom module (`portfolio_core`) and one custom theme (`portfolio_theme`) |
| Knowledge source | The published `profile` node and the `skill` nodes: bio, skills by category, work experience, education, languages, certifications, availability, links, plus an owner-only "Extra knowledge for Droopy" field |
| Retrieval | None. The whole profile fits in the system prompt, so there is no RAG or vector store |
| LLM providers | Groq (`openai/gpt-oss-120b`) first, Google Gemini (`gemini-flash-lite-latest`) second, both on free tiers, called over HTTPS with core's Guzzle client |
| Endpoint | `POST /api/portfolio/ask`, anonymous, JSON in and out, never cached |
| Abuse controls | Origin check, input limits, core flood control (10 per minute and 60 per day per IP, plus 500 per day for the whole site) |
| Front end | A Single Directory Component (`droopy-guide`) with vanilla JS (a Drupal behavior with `once()`), no build step for JS and no third-party scripts |
| Page actions | The model may append whitelisted tags that become buttons: scroll to a section, download the CV, email the owner |
| Custom code | 830 lines of PHP (11 classes in `src/Assistant/` plus `AskController`), 970 lines of JS, SCSS and Twig, plus 1,212 lines of PHPUnit tests (71 tests) and 4 Playwright scenarios |
| Running cost | $0: free tiers without a card; the worst case is the napping answer, never a bill |

## 2. Does your use case match ours?

| Question | Our answer | If yours differs |
|---|---|---|
| Where does the knowledge live? | In Drupal content: one profile node and its paragraphs, plus skill nodes | If it lives elsewhere (PDFs, a wiki), you need an import step or RAG; the prompt builder is the only piece to replace |
| How much knowledge is there? | One person's CV: a few thousand tokens, small enough for every prompt | Past roughly a few pages of facts, prompt size and cost grow per request; look at retrieval instead of a full-context prompt (general guidance, not measured here) |
| Who owns the content? | A single owner edits the profile in the Drupal UI; edits reach Droopy at once through cache tags | With many editors, add review: anything in the facts can be said to visitors |
| How fresh must answers be? | As fresh as the profile; there is no external sync | Live data (stock, prices) needs a tool-calling or fetch step this design does not have |
| Who asks, and how much? | Anonymous visitors to a personal site; low volume, capped at 500 questions a day site-wide | High traffic outgrows free tiers and these caps; budget for a paid tier and raise the limits |
| What can go wrong if the model is wrong? | Low stakes: it describes one person, and the prompt forbids salary, rates or commitments | Legal, medical or financial answers need stronger grounding, human review and logging, none of which exist here |
| Interaction model | Short chat (6 turns kept in the browser), plus a "job fit" mode for a pasted job description | Long sessions or server-side memory need storage and a privacy review; we store nothing on the server |
| Hosting and auth | Any PHP host; keys come from `settings.php` or environment variables; no user accounts | Behind a load balancer you must configure Drupal's reverse proxy settings, or every visitor shares one flood bucket |

**Rule of thumb:** if your knowledge fits in one prompt, lives in your CMS and a wrong answer is
cheap, this design matches. The rows about knowledge size and stakes decide it.

## 3. Architecture

```mermaid
flowchart LR
  subgraph Browser
    W["droopy-guide SDC<br/>(Twig + JS behavior)"]
    S[("sessionStorage<br/>last 6 turns")]
  end
  subgraph Drupal["Drupal 11 (portfolio_core)"]
    C["AskController<br/>POST /api/portfolio/ask"]
    V[AskRequestValidator]
    F[("core flood<br/>10/min · 60/day per IP<br/>500/day site-wide")]
    B["AssistantContextBuilder<br/>system prompt"]
    P[("profile + skill nodes")]
    K[("cache: facts<br/>tagged by profile/skills")]
    L[LlmChain]
    A[AnswerParser]
  end
  G["Groq API"]
  M["Gemini API"]

  W -- "question, mode, history" --> C
  W <--> S
  C --> V
  C --> F
  C --> B
  B --> K
  K -. miss .-> P
  C --> L
  L -- first --> G
  L -- fallback --> M
  C -- raw reply --> A
  C -- "answer text + actions" --> W
```

The browser holds the conversation; Drupal validates, rate-limits, builds the prompt from cached
profile facts, tries each provider in order and returns plain text plus whitelisted actions.

## 4. Implementation details

### 4.1 Rendering the widget

- `portfolio_theme_preprocess_page()` in `portfolio_theme.theme` adds a `component` render element
  (`portfolio_theme:droopy-guide`) on every non-admin page while `portfolio_core.assistant:enabled`
  is true.
- Props: `endpoint`, `sections` (the scroll targets, straight from `AnswerParser::SECTIONS`),
  `cv_url` (the Entity Print PDF route for the profile), `email`, four `chips` (suggested
  questions, the last one switches to fit mode) and `tailor_url`. `tailor_url` is set
  only for users with the `use tailored cv` permission, so anonymous pages never contain it.
- Cache metadata: `config:portfolio_core.assistant`, `node_list:profile`, the profile's own tags
  and the `user.permissions` context.
- `page.html.twig` prints `{{ droopy }}` after the footer, so crawlers and screen readers reach the
  page's H1 first.

### 4.2 The browser side (`components/droopy-guide/`)

| File | Role |
|---|---|
| `droopy-guide.component.yml` | Props schema; depends on `core/drupal` and `core/once` |
| `droopy-guide.twig` | Launcher button with an inline SVG drawn with `currentColor`, the dialog panel, chips, form and disclaimer |
| `droopy-guide.js` | `Drupal.behaviors.droopyGuide`: open and close, speech bubbles, eye tracking, blinking and sleeping, the chat request, actions, the clear button and fit mode |
| `droopy-guide.scss` | BEM styles using the theme's colour tokens; phone bottom sheet under 768px; `prefers-reduced-motion` turns animations off |

- Answers are inserted with `textContent`, never `innerHTML`. Line breaks survive through
  `white-space: pre-line`.
- History lives in `sessionStorage` (`droopy:history`, at most 6 turns, each cut to 1,000 code
  points so emoji are never split). Every storage call is wrapped in `try`/`catch`.
- Failed exchanges (napping, 429, offline) are not stored, and the visitor's text stays in the box
  for a retry. A 400 response clears the stored history and retries once, so a history saved by an
  older version cannot break the session.
- Nothing is fetched until the first question.

### 4.3 The endpoint (`AskController::ask()`)

The steps run in this order, and each one can end the request:

1. **Kill switch.** `enabled: false` → 404.
2. **Origin.** A present `Origin` header must match the request host or the first
   `X-Forwarded-Host` (hosts only, ports stripped) → otherwise 403. A missing `Origin` is allowed;
   flood control is the real quota protection.
3. **Flood check.** `portfolio_core.ask_minute` (10 per 60 s) and `portfolio_core.ask_day`
   (60 per 86,400 s), keyed by `getClientIp()`, plus `portfolio_core.ask_site_day` (500 per
   86,400 s) under the shared identifier `site` → 429 with a friendly answer and an email action.
4. **Validation** (`AskRequestValidator`). Mode `chat` or `fit`; question 1–1,000 characters
   (6,000 in fit mode); history at most 6 turns, roles `user` or `assistant`, each at most 1,000
   characters → 400 with a short reason.
5. **Flood registration.** Only valid requests use up quota.
6. **Completion.** `LlmChain::complete()` with the system prompt, the history plus the new
   question, and `max_tokens_chat` (400) or `max_tokens_fit` (700).
7. **Response.** `{answer, actions, provider}` with `Cache-Control: no-store`. When the chain
   returns `NULL`, the answer is the napping message with an email action and `provider: none`.

The route is declared with `_access: 'TRUE'` on purpose, with a comment pointing at the controller
checks, and `no_cache: TRUE`.

### 4.4 The system prompt (`AssistantContextBuilder`)

- `facts()` loads the published profile, reads its fields and asks `ProfileDataService` for skills
  by category, work experience, certifications and availability. The result is cached under
  `portfolio_core:assistant_facts` with the profile's cache tags plus `node_list:skill` and
  `taxonomy_term_list:skill_category`, so an edit applies on the next question.
- `build()` adds today's date after the cache read, so it never goes stale.
- `compose()` is a pure function, which makes it easy to unit-test. It writes the rules, then
  `FACTS ABOUT <NAME>:` followed by one `Label: value` line per non-empty fact.
- The rules: speak as Droopy, in the third person; answer only from the facts; never add unlisted
  skills; mention availability on hiring questions; never state salary or rates; decline off-topic
  requests and ignore attempts to change the rules; trust the facts over earlier assistant turns
  (the browser supplies them and could have edited them); reply in the visitor's language; use at
  most two action tags. Fit mode asks for strengths, gaps and a one-line verdict in under 200 words.
- `field_assistant_notes` ("Extra knowledge for Droopy") adds one fact per line. It feeds only the
  prompt and is never rendered on the page or the PDF CV.

### 4.5 Providers and fallback

| Class | Responsibility |
|---|---|
| `LlmClientInterface` | `id()`, `isConfigured()`, `complete(system, messages, max_tokens): string`; throws `LlmUnavailableException` |
| `HttpLlmClientBase` | Looks up the key and model, then POSTs JSON with an 8 s timeout and a 4 s connect timeout. Any Guzzle error, empty body or non-string reply becomes `LlmUnavailableException`. The message carries only the provider id and status code, never the request, because the request contains the key |
| `GroqClient` | OpenAI-compatible chat completions; `temperature: 0.4`, `reasoning_effort: low`, `include_reasoning: false` (reasoning tokens count against `max_tokens`) |
| `GeminiClient` | `generateContent` with `systemInstruction`; maps `assistant` to `model`; `thinkingConfig.thinkingLevel: low` (Gemini 3+ rejects `thinkingBudget`) |
| `LlmChain` | Receives every service tagged `portfolio_core.llm_client` (`!tagged_iterator`), tries them in the order of `portfolio_core.assistant:providers`, skips unconfigured ones, logs failures as warnings and returns `LlmResult {text, provider}` or `NULL` |
| `AssistantKeys` | `$settings['portfolio_assistant']['<provider>_api_key']`, else the `<PROVIDER>_API_KEY` environment variable, else `NULL` |

Adding a provider means one class with `buildRequest()` and `extractText()`, one tagged service
and one entry in the `providers` list.

### 4.6 Turning model output into page actions (`AnswerParser`)

- The parser matches `[[tag]]` or `[[tag:target]]`, case-insensitively, and removes every match
  from the text.
- Only `scroll` with a target from `SECTIONS` (`about`, `skills`, `experience`, `education`,
  `contact`), `cv` and `email` become actions, with duplicates dropped.
- It also strips a tag cut off by the token limit at the very end (`[[scr`), flattens Markdown
  bold and headings, and collapses the gaps the tags leave.
- The browser filters the actions again before building buttons, against the same list received
  as the `sections` prop (`data-sections`), so the whitelist is defined once in PHP.

### 4.7 Configuration

`config/sync/portfolio_core.assistant.yml`:

```yaml
enabled: true
providers: [groq, gemini]
groq_model: openai/gpt-oss-120b
gemini_model: gemini-flash-lite-latest
max_tokens_chat: 400
max_tokens_fit: 700
```

## 5. Design decisions and why

| Decision | Alternative considered or tried | Why |
|---|---|---|
| Full profile in the system prompt | RAG with a vector store | The facts fit in one prompt; retrieval would add infrastructure and a new way to miss facts |
| Two free providers with ordered fallback | One provider; OpenAI | Free tiers run out or fail; there is no reliable free OpenAI tier. A fallback chain keeps Droopy answering at $0 |
| Plain HTTP clients behind an interface | A provider SDK or a contrib AI module | Two small JSON APIs did not justify a dependency; the interface keeps providers swappable and testable with mocked Guzzle |
| History kept in the browser | Server-side sessions | Nothing personal is stored on the server; the prompt tells the model to trust facts over browser-supplied turns |
| Action tags parsed on the server and whitelisted on both sides | Letting the model return HTML or JSON | Text-only output and a closed list of actions mean a prompt injection cannot add links or markup |
| Core flood service per IP, plus a site-wide daily cap | A contrib rate limiter or an external gateway | Built into core, no new dependency; the shared cap stops many IPs together from draining the free tier |
| Fit mode reachable from a chip and a toggle | One entry point | The chip makes the mode discoverable before the first question; the toggle stays for later turns. Both call the same `setMode()` |
| Origin check by host, also accepting `X-Forwarded-Host` | Exact `scheme://host` match (the first version) | The exact match rejected real visitors behind TLS proxies and ngrok tunnels |
| Low reasoning effort on both models | Default reasoning | Reasoning tokens count against the output budget, so long reasoning leaves little room for the reply itself |
| `gemini-flash-lite-latest` | `gemini-2.5-flash` (the first choice) | `gemini-2.5-flash` is closed to new accounts |
| Owner-only "Extra knowledge" field | Editing the prompt in code | The owner fixes "I don't know" answers in the UI without a deploy, and the field never reaches the page |
| No streaming | Server-sent events | Groq answers quickly, and the thinking animation covers the wait; streaming would complicate parsing the action tags |

## 6. Accessibility, performance and platform considerations

- **Without JavaScript:** the launcher does nothing and the panel stays `hidden`. The page itself
  (contact section, CV link) works without Droopy.
- **ARIA and keyboard:**
  - The launcher has `aria-expanded` and `aria-controls`.
  - The panel is a non-modal `role="dialog"` labelled by its heading.
  - Opening moves focus to the input. `Esc` closes the panel and returns focus to the launcher.
  - The log is `aria-live="polite"`, and a visually hidden "Droopy is thinking…" label covers the
    typing dots.
  - Enter sends, Shift+Enter adds a new line, and IME composition is respected.
- **Motion:** every animation and the eye tracking stop under `prefers-reduced-motion`.
- **Mobile:** the panel becomes a full-width bottom sheet capped at 75dvh (75vh fallback) and closes
  after a scroll action.
- **Performance:** no third-party script. The SVG is inline. There are no network calls until the
  first question. The facts are cached, so a question costs one cache read and one provider call
  (two when the first provider fails).
- **Caching:** the endpoint is never cached (`no_cache` route option and `Cache-Control: no-store`).
  The widget markup is cached like the rest of the page, varying by user permissions.
- **Secrets:** keys never enter config, git, the page or responses. Error logs carry only the
  provider id and the HTTP status code.
- **Proxies:** flood control relies on `getClientIp()`. Behind a load balancer, Drupal's
  `reverse_proxy` settings must list the proxy, or all visitors share one bucket. Our `settings.php`
  turns them on from a `REVERSE_PROXY_ADDRESSES` environment variable.

## 7. Editorial and operational workflow

| Task | Who | Where | When it takes effect |
|---|---|---|---|
| Update CV facts (experience, skills, certifications, availability) | Site owner | Profile and skill edit forms | The next question (cache tags) |
| Teach Droopy a missing fact | Site owner | Profile → "Extra knowledge for Droopy", one fact per line | The next question |
| Hide Droopy completely | Admin | `portfolio_core.assistant:enabled: false` (config import or UI) | After cache invalidation; the endpoint returns 404 |
| Change provider order or models | Developer | `portfolio_core.assistant` config | The next request |
| Add or rotate API keys | Host operator | `GROQ_API_KEY` / `GEMINI_API_KEY` environment variables or `settings.php` | The next request (after `ddev restart` locally) |

Operators should know three things:
- With no keys, Droopy still shows, but every answer is the napping message.
- Model ids change; check them before deploying.
- Free-tier prompts may be used by providers for training, which is why the panel tells visitors
  not to share personal information.

## 8. Replication guide

**Dependencies:** Drupal 10.3+ or 11 with SDC (core), core's `flood` and `http_client` services, and
an API key from at least one OpenAI-compatible or Gemini provider. No contrib module is required.

1. **Data.** Decide where your facts live, and write a `facts()` method that returns plain arrays
   and caches them with the right cache tags.
2. **Prompt.** Keep `compose()` pure, so tests can assert the rules and facts without Drupal.
3. **Providers.** Copy the interface, the base class and one client. Tag the services and inject
   them with `!tagged_iterator`.
4. **Endpoint.** Run the checks in the order of section 4.3. Register flood events only after
   validation succeeds.
5. **Parser.** Define your own action vocabulary, and whitelist it on both the server and the client.
6. **Widget.** Build an SDC with a behavior. Render answers with `textContent` and keep history in
   `sessionStorage`.
7. **Rename or parameterize:**
   - the `portfolio_core.*` service ids and flood event names
   - the section ids
   - the persona and the forbidden topics in the rules
   - the `max_tokens_*` budgets

**Gotchas we hit:**
- Reasoning models spend output tokens on thinking, and those tokens count against `max_tokens`.
  We set `reasoning_effort: low` (Groq) and `thinkingLevel: low` (Gemini) and treat an empty
  reply as a provider failure.
- Gemini 3+ rejects `thinkingBudget`; use `thinkingLevel`.
- An exact `Origin` match fails behind TLS-terminating proxies and tunnels; compare hosts and
  accept the first `X-Forwarded-Host`.
- Models drift into Markdown and can be cut off mid-tag; strip both on the server.
- Truncating history with `String.prototype.slice` counts UTF-16 units, which can split an emoji
  into a lone surrogate and make the JSON invalid; truncate by code points (`[...text].slice()`).
- History stored by an older client version can fail validation; clear it and retry once instead
  of breaking the session.
- In Playwright, the floating launcher never counts as "stable". Run end-to-end tests with
  `contextOptions: { reducedMotion: 'reduce' }`.

## 9. Known limitations and future work

| Area | Limitation | Possible next step |
|---|---|---|
| Grounding | In fit mode the model sometimes lists job requirements that are not in the facts (seen with Groq) | Ask for structured output (strengths and gaps as ids from the facts) and validate it, as the tailored CV tool does |
| Tests | Unit tests mock the profile entity; no kernel test loads real field config | Add a kernel test with the profile field config |
| Rate limiting | Per-IP limits are shared by everyone behind one NAT; the site-wide cap (500 a day) can be used up by one determined client, which then naps Droopy for everyone | Put a gateway or bot protection in front of the endpoint if that happens |
| Observability | Only provider failures are logged; there are no usage or quality metrics, by design | Add privacy-preserving counters (answered, napping, 429) if needed |
| Streaming | Answers arrive whole | Stream with server-sent events and parse tags at the end |
