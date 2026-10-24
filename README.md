# Portfolio

Personal portfolio site built on Drupal 11. The site serves two purposes: professional presentation and a living technical showcase where each major feature is implemented using a different Drupal pattern — the codebase itself is the portfolio.

The public site is a single-page profile at `/` (hero, about, skills, experience, education, contact) with a downloadable PDF CV and **Droopy**, an AI guide that answers visitors' questions about Ricardo. Projects, articles, feature demos and skill pages are built but currently disabled (see [Disabled sections](#disabled-sections)). A separate Next.js app, the **Tech Lab** at `/headless`, consumes Drupal's JSON:API.

## How this repo is built

Features are built with an AI coding agent working under explicit rules, and every step leaves a trace in the repo:

- **[`CLAUDE.md`](CLAUDE.md)** sets the rules: coding standards, the light/dark token system, the git workflow, testing and the spec → plan → test-first → verify → review → document loop.
- **[`docs/specs/`](docs/specs/)** holds one design spec per feature (intent, non-goals, risks, decisions), each with dated notes on where the code drifted from the plan.
- **[`docs/studies/`](docs/studies/)** holds technical studies for outside developers, starting with [Droopy](docs/studies/droopy-ai-guide.md). They are written with a project skill ([`.claude/skills/technical-study`](.claude/skills/technical-study/SKILL.md)).
- **Tests:** PHPUnit for every service, Playwright smoke tests for what visitors see, and CI that runs `composer audit`, the unit tests and Drupal coding standards on every push.

## Tech Stack

- **CMS:** Drupal 11
- **PHP:** 8.3
- **Database:** MariaDB 11.8
- **Local environment:** DDEV
- **Theme:** Custom (`portfolio_theme`) — no base theme, SDC components, Dart Sass, BEM
- **Admin theme:** Gin
- **Search:** Search API + Database backend + Facets
- **PDF:** Entity Print + dompdf
- **AI:** Groq (`openai/gpt-oss-120b`) with Gemini (`gemini-flash-lite-latest`) fallback — free tiers, plain HTTP via Guzzle
- **CI:** GitHub Actions — `composer audit`, PHPUnit, phpcs (Drupal + DrupalPractice)
- **CSS:** Custom properties (design tokens), no frameworks
- **Tech Lab:** Next.js app in [`nextjs/`](https://github.com/Ricardo0597/portfolio-headless) (git submodule), served at `/headless` by a DDEV `nodejs` container
- **Tests:** PHPUnit (unit), Playwright (end-to-end smoke tests against DDEV)

## Local Setup

### Requirements

- [DDEV](https://ddev.com/) v1.23+
- Docker

### Install

```bash
git clone --recurse-submodules git@github.com:Ricardo0597/portfolio.git
cd portfolio
ddev start
ddev composer install
ddev drush site:install --existing-config --account-name=admin --account-pass=admin -y
ddev drush cr
```

Site will be available at **https://portfolio.ddev.site**.

Default admin credentials: `admin` / `admin` (local only).

### AI keys (optional)

Droopy and the tailored CV need at least one provider key. Keys never go in config or git — put them in `.ddev/.env` (git-ignored) and restart:

```bash
GROQ_API_KEY=...
GEMINI_API_KEY=...
```

```bash
ddev restart
```

Without keys Droopy still renders, but every answer is a friendly "napping" message. Set `enabled: false` in `portfolio_core.assistant` to hide it entirely.

## Common Commands

```bash
# Cache
ddev drush cr

# Config
ddev drush cex -y       # export config after structural changes
ddev drush cim -y       # import config

# Theme build (Dart Sass)
ddev theme-build        # install deps, compile SCSS, rebuild cache
ddev theme-watch        # watch mode for SCSS

# Expose site publicly (ngrok)
ddev share              # expose site via ngrok tunnel, prints a public URL

# End-to-end smoke tests (Playwright, against the running DDEV site)
npm install
npx playwright test

# Unit tests and coding standards (also run in CI)
ddev exec ./vendor/bin/phpunit -c web/core web/modules/custom/portfolio_core/
ddev exec ./vendor/bin/phpcs --standard=Drupal,DrupalPractice \
  --extensions=php,module,inc,install,theme,yml --ignore=node_modules,*.css,*.js \
  web/modules/custom web/themes/custom/portfolio_theme
```

## Project Structure

```
portfolio/
├── .claude/skills/technical-study/ # project skill that writes docs/studies/
├── .github/workflows/ci.yml        # audit, unit tests, phpcs
├── config/sync/                    # Drupal config (source of truth)
├── docs/
│   ├── specs/                      # design spec per feature, with drift notes
│   └── studies/                    # technical studies for outside developers
├── nextjs/                         # git submodule: Next.js Tech Lab (/headless)
├── tests/e2e/                      # Playwright smoke tests
├── web/
│   ├── modules/custom/
│   │   └── portfolio_core/         # single custom module
│   │       ├── src/Assistant/      # Droopy: LLM clients, fallback chain, prompt, parser, validator
│   │       ├── src/Controller/     # AskController (POST /api/portfolio/ask)
│   │       ├── src/Cv/             # tailored CV: prompt, plan validator, PDF generator
│   │       ├── src/EventSubscriber/# disabled sections redirect, Entity Print CSS
│   │       ├── src/Form/           # TailoredCvForm
│   │       ├── src/Plugin/Block/   # CurrentSkillsBlock
│   │       ├── src/Plugin/rest/    # Projects REST endpoint
│   │       ├── src/Service/        # ProfileDataService
│   │       ├── src/TwigExtension/  # skill_level_label, reading_time
│   │       └── tests/src/Unit/     # PHPUnit unit tests
│   └── themes/custom/
│       └── portfolio_theme/
│           ├── components/         # SDC components
│           │   ├── droopy-guide/
│           │   ├── theme-toggle/
│           │   ├── social-links/
│           │   ├── project-card/
│           │   ├── skill-card/
│           │   ├── article-teaser/
│           │   └── feature-badge/
│           └── scss/               # Dart Sass source
└── .ddev/
    └── commands/web/               # theme-build, theme-watch
```

## Feature Inventory

Each feature is an intentional showcase of a different Drupal pattern:

| Feature | Pattern |
|---|---|
| Featured skills block | Block Plugin + Dependency Injection |
| `/api/portfolio/projects` | REST Resource Plugin + cacheable `ResourceResponse` |
| `skill_level_label`, `reading_time` Twig filters | Twig Extension service |
| Project search + filters | Search API + Facets + Views |
| Project/Article listings | Preprocess hooks + SDC view mode templates |
| SEO metadata | Metatag + Simple Sitemap config |
| Headless pattern | JSON:API with filters, sparse fieldsets, includes |
| Single-page profile at `/` | Front page = profile node, anchored sections, sticky nav with scroll-spy, `social-links` SDC |
| Disabled sections | Config-driven Event Subscriber (`portfolio_core.settings`) |
| Availability (start date, work mode, engagement, schedule) | List/string fields + `ProfileDataService` |
| PDF CV export (one A4 page) | Entity Print + dompdf + `pdf` view mode + preprocess hook |
| Droopy AI guide (`POST /api/portfolio/ask`) | SDC with JS + JSON endpoint + provider-agnostic LLM chain (tagged services) + flood control + action-tag whitelist |
| Tailored CV (private) | Multi-step Form API + LLM plan with fact-id references + validator + Entity Print render with request-scoped override |
| Structured data (node pages) | `hook_page_attachments` + JSON-LD (`Person` with `hasCredential`, `WebSite`, `SoftwareSourceCode`, `TechArticle`) |
| Structured data (listing pages) | `hook_views_post_render` + JSON-LD `ItemList` |
| Light/dark switch | `theme-toggle` SDC + CSS custom property token swap, follows OS preference, `localStorage` persistence |
| Mobile navigation | Animated hamburger drawer + `aria-expanded` + Drupal behavior |
| Project full page | `node--project--full.html.twig` + preprocess |
| Admin local task tabs | Template override + BEM CSS |
| Tech Lab (`/headless`) | Next.js consuming JSON:API through an nginx proxy in DDEV ([spec](docs/specs/2026-05-13-nextjs-headless-design.md)) |

### Public endpoints

| Endpoint | Method | Notes |
|---|---|---|
| `/api/portfolio/projects?_format=json` | GET | Custom REST resource, anonymous, cacheable |
| `/api/portfolio/ask` | POST | Droopy; JSON in and out, never cached, rate-limited |
| `/jsonapi/...` | GET | Core JSON:API, read-only |
| `/node/{id}?_format=json` | GET | Core REST, GET only (no write methods, no Basic Auth) |
| `/print/pdf/node/{id}` | GET | Entity Print PDF of the profile (the CV) |

## Content Types

| Type | Path pattern |
|---|---|
| Profile | `/` (front page; `/about` 301s to `/`) |
| Project | `/projects/[title]` |
| Skill | `/skills/[title]` |
| Article | `/articles/[title]` |
| Feature Demo | `/features/[title]` |

## Droopy (AI guide)

A floating mascot that answers questions about Ricardo from the profile's facts (experience, skills, certifications, availability and the private "Extra knowledge for Droopy" field).

- **Endpoint:** `POST /api/portfolio/ask` — question ≤ 1000 chars (6000 in job-fit mode), history ≤ 6 turns.
- **Fallback:** providers are tried in the order set in `portfolio_core.assistant`; if all fail the visitor gets a "napping" answer with an email link.
- **Limits:** 10 questions/minute and 60/day per IP (core flood); foreign `Origin` → 403.
- **Safety:** answers are rendered as text only; the model can trigger UI actions only through whitelisted tags (`[[scroll:…]]`, `[[cv]]`, `[[email]]`).
- **How it works and how to replicate it:** [technical study](docs/studies/droopy-ai-guide.md).

## Tailored CV (private)

`/admin/portfolio/tailored-cv` (permission `use tailored cv`, menu: Content › Tailored CV): paste a job description → AI draft → edit summary, bullets, highlights and skills → download `<Site-Name>-CV-<Company>.pdf`. The AI may only reference existing profile facts by id; a validator drops anything unknown. Nothing is saved to content or logs. Logged-in users with the permission can also type `/tailored-cv` in Droopy for a shortcut.

## Disabled sections

Projects, articles, feature demos and skills redirect (302) to `/`, driven by `portfolio_core.settings` (`disabled_bundles`, `disabled_routes`). JSON:API and REST stay available, and users with `bypass node access` are not redirected. Re-enable a section by removing it from the config and running `ddev drush cim -y`.

## Production checklist

1. Set `GROQ_API_KEY` / `GEMINI_API_KEY` as host environment variables.
2. Set `REVERSE_PROXY_ADDRESSES` (comma-separated proxy IPs/CIDRs) so flood control sees the visitor's IP (otherwise all visitors share one quota). `settings.php` is git-ignored: the host's copy needs the env-driven `reverse_proxy` block shown in CLAUDE.md.
3. Check the model ids in `portfolio_core.assistant` still exist.

## Development Notes

- All structural changes require a config export before committing: `ddev drush cex -y`
- Never run `drush` or `composer` outside DDEV
- Stage files explicitly — no `git add .`
- One custom module only: `portfolio_core`
- Commit format: [Conventional Commits](https://www.conventionalcommits.org/)
- All component colours must use `var(--color-*)` tokens — the light/dark toggle swaps nine CSS custom properties at runtime; hardcoded colours break light mode. See CLAUDE.md for full SCSS patterns.
