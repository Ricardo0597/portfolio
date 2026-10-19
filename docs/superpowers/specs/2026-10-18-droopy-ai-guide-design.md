# Droopy AI Guide — Design

**Date:** 2026-10-18
**Phase:** 2 (builds on `2026-10-18-single-page-rebrand-design.md`)
**Status:** Draft, awaiting approval

## Intent

Droopy — the drop mascot already used as the site logo — becomes a floating,
animated guide in the bottom-right corner that answers visitors' questions
about Ricardo using an LLM. It should feel alive and modern, make recruiters
smile, and get them to the useful things fast: experience, availability, CV,
email. It must cost **$0** to run.

It is also a showcase piece: a custom Drupal endpoint, a provider-agnostic
LLM client with fallback, flood control and an SDC with its own JS.

## Goals

1. Floating Droopy (bottom-right) on every public page: idle float, blinks,
   eyes follow the cursor; a speech bubble types *"Ask me about Ricardo 👋"*
   a few seconds after load.
2. Clicking Droopy opens a chat panel on the right (bottom sheet on phones)
   with suggested-question chips, a text box and a short AI disclaimer.
3. Answers come from a free LLM through `/api/portfolio/ask`, grounded only
   in Ricardo's profile data plus his availability.
4. Droopy proactively surfaces availability: **available to start
   immediately, remote only, flexible on time zones and project length,
   open to full-time, part-time, contract and freelance.**
5. Answers can act on the page: scroll to a section, offer the CV download,
   offer the email link.
6. "Is Ricardo a fit?" mode: a visitor pastes a job description and gets a
   short, honest match summary.
7. Never breaks: when every provider is down or out of quota, Droopy
   answers with a friendly "napping" message and the email link.

## Non-goals

- Streaming responses (v1 waits for the full answer; Groq is fast and the
  thinking animation covers latency).
- Storing conversations server-side, analytics dashboards, user accounts.
- RAG / vector search — the full profile fits in the prompt.
- Voice input/output.
- In-browser LLM (download size makes it a bad first impression).

## Anti-goals

- An API key reaching the browser.
- Droopy inventing facts (employers, dates, skills, salary) or committing
  Ricardo to anything beyond the availability facts below.
- Answering off-topic requests (homework, code generation, jailbreak games)
  — it politely redirects to Ricardo.
- Any paid usage or a credit card on file.
- Droopy covering content on phones or trapping keyboard focus.
- Animations ignoring `prefers-reduced-motion`.

## Availability facts (new, editable)

Stored as new fields on the **profile** node so Ricardo edits them in the UI,
and also shown visibly on the page (hero badge + Contact section), not only
inside the chat:

| Field | Type | Value now |
|---|---|---|
| `field_available_from` | string | `Immediately` |
| `field_work_mode` | list (string), single | `remote` → "Remote only" |
| `field_engagement_types` | list (string), multiple | `full_time`, `part_time`, `contract`, `freelance` |
| `field_schedule_flexibility` | string | `Flexible with time zones and project length` |

The hero gets a small "● Available for work" badge when
`field_available_from` is not empty; the Contact section lists all four.

## How it works

### Frontend — SDC `components/droopy-guide/`

- `droopy-guide.component.yml`, `.twig`, `.scss`, `.js` (SDC-attached JS,
  Drupal behavior with `once()`, no jQuery).
- Embedded once in `page.html.twig` when the assistant is enabled
  (`portfolio_core.assistant:enabled`); passed `cv_url`, `email`, and the
  suggested questions from preprocess.
- **Mascot:** inline SVG built from the four paths in `images/logo.svg`
  (drop, left eye, right eye, smile) with `stroke="currentColor"` so it works
  in both themes. Animations (CSS + small JS):
  - idle float (translateY loop), blink every 4–7 s (scaleY on eyes),
    pupils/eye-groups shift toward the cursor (clamped few px);
  - bubble types the greeting after ~3 s, hides after ~8 s, rotates later
    messages: *"Ricardo is available now — ask me how he can help"*,
    *"Want the 30-second version of his experience?"* (shown when the
    Experience section enters view);
  - on open: squash-and-stretch "hello"; while waiting: eyes look up +
    typing dots; on error/quota: eyes closed + "z z z";
  - idle 60 s with panel closed: falls asleep (closed eyes, z's), wakes on
    hover;
  - theme switch to dark: a quick slow blink.
  - All motion disabled under `prefers-reduced-motion` (static Droopy, no
    typing effect).
- **Panel:** `role="dialog"` (non-modal), labelled "Ask about Ricardo";
  focus moves to the input on open, `Esc` closes and returns focus to
  Droopy; answers announced via `aria-live="polite"`. Desktop: 380px panel
  anchored right; ≤767px: full-width bottom sheet, max 75vh.
- **Chips** (shown before the first question): "Is he available?",
  "What's his Drupal experience?", "What has he built?", "Is he a fit for
  my role?" (switches to fit mode with a larger textarea).
- **Actions** returned by the API render as buttons under the answer:
  `scroll:<section>` → smooth-scroll + highlight that section and close the
  sheet on phones; `cv` → "Download CV (PDF)"; `email` → mailto button.
- Conversation (last 6 turns) and "dismissed bubble" state kept in
  `sessionStorage` (wrapped in try/catch).
- Footer line in the panel: *"AI answers based on Ricardo's CV. Don't share
  personal information."*
- Replies in the visitor's language (the model mirrors the question).

### Backend — `portfolio_core`

**Route:** `POST /api/portfolio/ask` → `AskController::ask()`.
Request JSON: `{ "question": string, "mode": "chat"|"fit", "history": [{role, content}] }`.
Response JSON (`JsonResponse`, `Cache-Control: no-store`):
`{ "answer": string, "actions": [{ "type": "scroll", "target": "experience" } | { "type": "cv" } | { "type": "email" }], "provider": "groq"|"gemini"|"none" }`.

Limits: `question` ≤ 1,000 chars (chat) / 6,000 chars (fit); history ≤ 6
items, each ≤ 1,000 chars; reject with 400 otherwise. Same-origin check on
the `Origin` header (403 if foreign). Anonymous access allowed.

**Services (DI, unit-tested):**

- `AssistantContextBuilder` — builds the system prompt from
  `ProfileDataService` + profile node fields (bio, skills by category, work
  experience, education, languages, links) + availability facts. Cached
  with the profile node's cache tags so edits apply instantly.
- `LlmClientInterface::complete(string $system, array $messages, int $max_tokens): string`
  with `GroqClient` and `GeminiClient` (Guzzle `http_client`), each throwing
  `LlmUnavailableException` on 429/5xx/timeout (8 s).
- `LlmChain` — tries providers in configured order, returns
  `{text, provider}`; on all failing returns the napping message.
- `AnswerParser` — extracts action tags the model is instructed to append
  (`[[scroll:experience]]`, `[[cv]]`, `[[email]]`), whitelists them
  (sections: about, skills, experience, education, contact), strips them
  from the text.
- Flood control (`flood` service): 10 requests / minute and 60 / day per
  IP; over the limit → 429 with a friendly message Droopy shows.

**System prompt rules** (exact wording is the builder's): speak as Droopy,
Ricardo's mascot, in third person about Ricardo; answer only from the
provided facts; if unknown, say so and suggest emailing; proactively
mention availability when the question is about hiring, rates, start dates
or fit; never state salary or rates (suggest email); keep answers under
~120 words (fit mode: strengths, gaps, verdict, under ~200 words); refuse
off-topic requests politely; append action tags when relevant.

**Config (exported, no secrets):** `portfolio_core.assistant`
```yaml
enabled: true
providers: [groq, gemini]
groq_model: 'openai/gpt-oss-120b'
gemini_model: 'gemini-2.5-flash'
max_tokens_chat: 400
max_tokens_fit: 700
```
Model ids must be checked against each provider's current list at build
time (Groq retired some Llama models in Aug 2026).

**Secrets:** API keys only in `settings.php` from env vars:
`$settings['portfolio_assistant']['groq_api_key'] = getenv('GROQ_API_KEY');`
(same for `GEMINI_API_KEY`). Locally via `.ddev/.env` (git-ignored); on the
host via its env-var settings. A missing key just skips that provider.

## Cost

Both providers on free tiers without a card: Groq (~30 req/min, ~1,000/day
per model reported) and Google Gemini (limits shown in AI Studio). Flood
limits keep one visitor from burning the quota. Worst case is the napping
fallback — never a bill. Free-tier prompts may be used for training; only
public CV data and visitor questions are sent, and the disclaimer covers it.

## Constraints

- CLAUDE.md rules (DI, strict types, `t()`, BEM, tokens, light-mode rules,
  no jQuery, `once()`), config export, explicit staging.
- Endpoint documented in PROMPT.md Feature Inventory under `/api/portfolio/`.
- Must not hurt page load: widget JS/CSS small, SVG inline, no third-party
  scripts; nothing fetched until the first question.

## Decisions made by Claude (owner to check)

1. "Any kind of time frame" is read as **flexible with time zones and
   project length** (wording editable in the field).
2. Availability is also shown visibly (hero badge + Contact), not only in
   the chat.
3. Droopy never discusses salary/rates — it suggests emailing.
4. Groq first, Gemini fallback; no OpenAI (no reliable free tier).
5. No streaming in v1.
6. Kill switch: `portfolio_core.assistant:enabled: false` hides the widget
   and makes the endpoint return 404.

## Left to the builder

- Exact animation curves/timings, bubble copy rotation timing, panel
  styling within tokens.
- Prompt wording within the rules above.
- Whether flood/origin checks live in the controller or a small service.

## Verification

- Unit tests: context builder (includes availability, omits empty fields),
  answer parser (whitelist, stripping, unknown tags dropped), chain
  (fallback order, napping on all-fail), request validation limits.
- Manual: questions about availability, Drupal experience, a pasted JD, an
  off-topic request, an injection attempt ("ignore your instructions…"),
  a Spanish question; action buttons work; 11th request in a minute → 429
  message; both keys removed → napping message.
- Both themes, 1280px and 375px, keyboard-only, reduced motion.
- No API key in page source or network responses.
