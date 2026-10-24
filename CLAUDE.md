# CLAUDE.md — Portfolio Project

## Project Overview

Personal portfolio site built on Drupal 11. The site serves two purposes:
1. Professional presentation: bio, projects, skills, articles.
2. Living technical showcase: every major feature is deliberately implemented
   using a different Drupal pattern so the codebase itself is the portfolio.

This file is the single source of truth for AI coding agents (Claude Code
reads it automatically) and for humans who want to know how the repo is
built. Read it completely before taking any action on this project.

---

## Local Environment

- **Tool:** DDEV
- **PHP:** 8.3
- **Node:** 24 in the DDEV web container (theme build, Playwright); 20 in the `nodejs` container that serves the Next.js Tech Lab
- **Docroot:** `web/`
- **Project URL:** https://portfolio.ddev.site

### DDEV command conventions

Always prefix Drush and Composer commands with `ddev`:

```bash
ddev drush <command>
ddev composer <command>
ddev start / ddev stop / ddev restart
```

Never run `drush` or `composer` directly outside DDEV unless explicitly told to.

### Useful DDEV shortcuts

```bash
ddev drush cr          # cache rebuild
ddev drush cex -y      # config export
ddev drush cim -y      # config import
ddev drush updb -y     # run update hooks
ddev drush en <module> # enable module
ddev theme-build       # compile SCSS → CSS (runs npm run build inside the theme)
ddev share             # expose site publicly via ngrok tunnel
```

---

## Repository and Directory Structure

```
portfolio/                          ← project root
├── .ddev/
│   ├── config.yaml
│   └── commands/
├── .claude/
│   └── skills/
│       └── technical-study/        ← project skill: writes docs/studies/*
├── .github/workflows/ci.yml        ← composer audit, PHPUnit, phpcs
├── config/
│   └── sync/                       ← exported config lives here
├── docs/                           ← see "Docs map" below
├── nextjs/                         ← git submodule: portfolio-headless (Tech Lab at /headless)
├── tests/
│   └── e2e/                        ← Playwright smoke tests (run against DDEV)
├── web/
│   ├── core/
│   ├── modules/
│   │   ├── contrib/
│   │   └── custom/
│   │       └── portfolio_core/     ← main custom module
│   ├── themes/
│   │   ├── contrib/
│   │   └── custom/
│   │       └── portfolio_theme/    ← custom theme
│   └── sites/
│       └── default/
├── CLAUDE.md                       ← this file
├── README.md                       ← overview, setup, Feature Inventory
├── package.json                    ← Playwright only
├── playwright.config.js
├── composer.json
└── composer.lock
```

Clone with `git clone --recurse-submodules`, or run
`git submodule update --init` after a plain clone. Changes to the Next.js app
are committed in `nextjs/` (its own repo) first, then the new pointer is
committed here.

---

## Docs Map

| Path | Public | What goes there |
|---|---|---|
| `README.md` | yes | What the site is, setup, architecture, the **Feature Inventory** table |
| `CLAUDE.md` | yes | How we work: standards, workflow, commands, testing, gotchas |
| `docs/specs/YYYY-MM-DD-<topic>-design.md` | yes | Design spec per feature: intent, goals, non-goals, risks, decisions, and dated "Code/doc drift found" sections |
| `docs/studies/<feature>.md` | yes | Technical study per shipped feature, written for outside developers who may replicate it |
| `docs/plans/YYYY-MM-DD-<topic>.md` | no (git-ignored) | Step-by-step implementation plans; working documents that go stale once the code lands |
| `docs/notes/` | no (git-ignored) | Personal study notes |
| `PROMPT.md` | no (git-ignored) | The original phase-by-phase bootstrap script |

Rules:
- Specs and studies must be true of the code as it is today. When code
  changes a documented behaviour, update the spec in the same commit, or add a
  dated drift section to it.
- Never put secrets, production data or private facts in any doc.
- Studies are written with the `technical-study` skill
  (`.claude/skills/technical-study/SKILL.md`), which first reconciles the spec
  against the code, then writes `docs/studies/<feature>.md`.

| Feature | Spec | Study |
|---|---|---|
| Droopy AI guide | `docs/specs/2026-10-18-droopy-ai-guide-design.md` | `docs/studies/droopy-ai-guide.md` |
| Tailored CV | `docs/specs/2026-10-22-tailored-cv-design.md` | `docs/studies/tailored-cv.md` |
| Single-page profile | `docs/specs/2026-10-18-single-page-rebrand-design.md` | — |
| Next.js Tech Lab | `docs/specs/2026-05-13-nextjs-headless-design.md` | — |
| PDF CV | `docs/specs/2026-04-20-entity-print-cv-pdf-design.md`, `docs/specs/2026-04-22-cv-pdf-pattern-refactor-design.md` | — |
| Light/dark switch | `docs/specs/2026-04-24-theme-mode-toggle-design.md` | — |
| ItemList JSON-LD | `docs/specs/2026-04-27-itemlist-schema-listing-pages-design.md` | — |

---

## AI-Assisted Development Workflow

Features are built with an AI coding agent under the rules in this file. The
agent proposes; the owner decides and reviews. Every feature goes through the
same loop:

1. **Brainstorm** the idea into a design spec (`superpowers:brainstorming`):
   intent, goals, non-goals, anti-goals, risks, and the decisions the agent
   made that the owner must check. Save it to `docs/specs/`.
2. **Plan** the implementation as small, test-first tasks
   (`superpowers:writing-plans`), saved to `docs/plans/` (private).
3. **Build test-first** (`superpowers:test-driven-development`): PHPUnit for
   services, plugins and controllers; Playwright for anything a visitor can
   see or click.
4. **Verify before claiming done** (`superpowers:verification-before-completion`):
   unit tests, phpcs, `ddev drush config:status` clean, Playwright suite green,
   and a manual check in the browser (both themes, 1280px and 375px).
5. **Review** (`superpowers:requesting-code-review` or `/code-review`), then
   fix or explicitly reject each finding.
6. **Reconcile docs**: update the spec's status and drift notes, `CLAUDE.md`
   gotchas and the README Feature Inventory.
7. **Write the technical study** (`technical-study` skill) for features worth
   showing to outside developers.

Agents must:
- Ask before outward-facing actions (push, PR, publishing) unless told to proceed.
- Never put API keys, tokens or personal data in code, config, docs or commits.
- Clean up Playwright MCP output (`.playwright-mcp/`) after browser sessions.
- Report test results as they are, including failures.

---

## Git Workflow

- **Main branch:** `main` (always deployable)
- **Feature branches:** `feature/<short-name>` (e.g., `feature/skill-content-type`)
- **Fix branches:** `fix/<short-name>`

### Commit format (Conventional Commits)

```
feat: add Skill content type with taxonomy reference
fix: correct pathauto pattern for project nodes
docs: add technical study for Droopy
chore: enable and configure metatag module
refactor: extract skill card into SDC component
```

### Co-authorship

Never add a `Co-Authored-By:` trailer to commit messages.

### Staging rules

Always stage files explicitly. Never use `git add .`:

```bash
git add web/modules/custom/portfolio_core/portfolio_core.module
git add config/sync/
```

Export config after every structural change before committing:

```bash
ddev drush cex -y
git add config/sync/
```

---

## Coding Standards

### PHP

- Drupal 11, PHP 8.3 strictly.
- Follow Drupal coding standards: https://www.drupal.org/docs/develop/standards
- Use dependency injection. No `\Drupal::service()` static calls inside classes.
- Static calls are acceptable only inside procedural hooks (`.module` files)
  where DI is not available.
- Strict types declaration required on all PHP files (blank line after
  `<?php`, as the Drupal coding standard requires):
  ```php
  <?php

  declare(strict_types=1);
  ```
- Type hints and return types required on all methods.
- PHPDoc blocks required on all classes and public methods.

### JavaScript

- No jQuery. Use native JavaScript and Drupal behaviors.
- Use `once()` correctly for behavior attachment:
  ```javascript
  // Correct
  Drupal.behaviors.portfolioFeature = {
    attach(context) {
      once('portfolio-feature', '.my-selector', context).forEach((el) => {
        // attach logic here
      });
    }
  };
  ```
- ES2020+ syntax is acceptable.
- No build step required unless explicitly specified. Use native modules or
  attach via `*.libraries.yml`.

### Twig

- Use Single Directory Components (SDC) for all reusable UI elements.
- Do not put logic in Twig. Prepare data in preprocess hooks or plugins.
- Use `{{ content }}` rendering via render arrays, not raw field access where
  Drupal caching and rendering pipeline should be respected.

### CSS

- Use CSS custom properties (variables) for theming tokens.
- No CSS frameworks (Bootstrap, Tailwind) unless explicitly requested.
- BEM naming convention for all custom classes.

### Light/Dark mode compatibility

The site has a light/dark mode toggle. Dark is the default. Light mode is activated by `[data-theme="light"]` on `<html>`, which overrides nine colour tokens in `scss/abstracts/_tokens.scss`.

**Rules for every new component:**

1. **Always use colour tokens** (`var(--color-*)`) — never hardcode colours except for static values that must be the same in both modes (e.g. `color: #fff` on a coloured background, status badge colours).

2. **`--color-accent` stays dark navy in both modes** (`#0f3460` dark / `#1e3a5f` light). Any text placed on an `--color-accent` background must be `color: #fff`, never `color: var(--color-text)` — which becomes near-black in light mode and is unreadable.

3. **`--color-highlight` (`#e94560`) is the same in both modes.** Text on it should always be `color: #fff`.

4. **SVG assets with hardcoded `stroke="white"` or `fill="white"` are invisible in light mode.** Either author the SVG with `currentColor` (and set `color` via CSS), or add `filter: invert(1)` under `[data-theme='light']` in `_main.scss`.

5. **Do not add `[data-theme="light"]` overrides inside component SCSS files.** Light-mode-specific fixes that cannot be expressed through tokens belong in the `[data-theme='light']` block in `scss/layout/_main.scss`.

**Quick self-check before committing any component:**

- Does it use `var(--color-text)` or `var(--color-text-muted)` on a dark fixed background? → Replace with `#fff`.
- Does it use a hardcoded light colour on any surface? → Replace with the appropriate token.
- Does it contain an SVG or `<img>` with hardcoded white strokes? → Use `currentColor` or add a light-mode `filter`.

### SCSS patterns for theme-compatible components

#### Token dual-layer system

`_tokens.scss` defines two layers:

- **SCSS variables** (`$color-*`) — compile-time only. Use these inside SCSS
  functions and mixins (e.g. `rgba(t.$color-highlight, 0.15)`). The output is a
  hardcoded hex/rgba value baked into the CSS; it does **not** respond to the
  `[data-theme='light']` override at runtime.
- **CSS custom properties** (`var(--color-*)`) — runtime. These are what the
  toggle swaps. Use them for all component colours that need to adapt.

Rule: **components use `var(--color-*)`. SCSS variables (`t.$`) are only for
feeding SCSS-level functions where a CSS custom property is not accepted.**

#### Canonical component pattern

```scss
// Good — all surfaces and text use runtime tokens.
.my-component {
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  color: var(--color-text);

  &__label {
    color: var(--color-text-muted);
    font-size: var(--text-sm);
  }

  &:hover {
    border-color: var(--color-highlight);
  }
}
```

#### Static colour exceptions

Some colours are intentionally the same in both modes and should be hardcoded:

| Value | Use case |
|-------|----------|
| `#fff` | Text on `--color-accent`, `--color-highlight`, or any fixed-colour background |
| `t.$color-status-live` / `t.$color-status-wip` / `t.$color-status-archived` | Status badge backgrounds — same in both modes |
| `t.$color-diff-beginner` / `t.$color-diff-intermediate` / `t.$color-diff-advanced` | Difficulty badge backgrounds |

```scss
// Good — status badge uses SCSS token directly (same in both modes).
.status-badge--live {
  background: t.$color-status-live;
  color: #fff;   // always white — token background is a fixed dark green
}

// Bad — using the CSS custom property for a surface with fixed-colour text.
.status-badge--live {
  background: t.$color-status-live;
  color: var(--color-text);   // becomes near-black in light mode → unreadable
}
```

#### The rgba() pattern

When you need a semi-transparent tint of a colour, SCSS `rgba()` requires a
compile-time value — CSS custom properties cannot be passed to it directly.
Use the SCSS token, and only for colours that are the same in both modes:

```scss
// Correct — highlight and status colours are static.
background: rgba(t.$color-highlight, 0.15);
background: rgba(t.$color-status-live, 0.15);

// Wrong — token value is dark-mode only; the tint will not adapt.
background: rgba(t.$color-bg, 0.5);   // use var(--color-bg) with opacity instead
```

For adaptive semi-transparent tints, use `color-mix()` or `opacity` on a child
element instead of `rgba()` with a theme token.

#### Gradient text

The `background-clip: text` gradient pattern works in both modes as long as
the gradient colours are CSS custom properties:

```scss
// Good — gradient adapts because it uses runtime tokens.
.heading {
  background: linear-gradient(135deg, var(--color-text) 0%, var(--color-text-muted) 100%);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}
```

#### Light-mode overrides

If a component has an edge case that cannot be expressed through tokens (e.g.
an image or SVG with hardcoded white strokes), add the fix to the
`[data-theme='light']` block at the bottom of `scss/layout/_main.scss`.
**Never** add a `[data-theme='light']` selector inside a component SCSS file.

```scss
// In scss/layout/_main.scss — the single override block.
[data-theme='light'] {
  .site-branding__logo,
  .site-footer__logo {
    filter: invert(1);
  }

  // Add new light-mode exceptions here, not in component files.
}
```

---

## Custom Module: portfolio_core

**Location:** `web/modules/custom/portfolio_core/`

This is the single custom module for the project. It handles:

- Content type definitions (if not done via UI + config export)
- Custom block plugins
- Custom REST endpoints
- Event subscribers
- Custom Twig extensions
- Preprocess hooks
- Any custom service or plugin

### Module structure

```
portfolio_core/
├── portfolio_core.info.yml
├── portfolio_core.module
├── portfolio_core.routing.yml
├── portfolio_core.services.yml
├── portfolio_core.permissions.yml
├── src/
│   ├── Assistant/                  ← Droopy: LLM clients, chain, prompt, parser, validator
│   ├── Controller/                 ← AskController
│   ├── Cv/                         ← tailored CV: prompt, plan validator, PDF generator
│   ├── EventSubscriber/            ← DisabledSectionsSubscriber, EntityPrintCssSubscriber
│   ├── Form/                       ← TailoredCvForm
│   ├── Plugin/
│   │   ├── Block/                  ← CurrentSkillsBlock
│   │   └── rest/                   ← ProjectsResource
│   ├── Service/                    ← ProfileDataService
│   └── TwigExtension/
└── tests/
    └── src/
        └── Unit/                   ← Assistant/, Cv/, and service tests
```

---

## Custom Theme: portfolio_theme

**Location:** `web/themes/custom/portfolio_theme/`

- Based on Drupal 11 core SDC support (no base theme needed unless Classy
  patterns are explicitly required).
- Admin theme: Gin.
- Frontend theme: portfolio_theme.

### Theme structure

```
portfolio_theme/
├── portfolio_theme.info.yml
├── portfolio_theme.libraries.yml
├── portfolio_theme.theme
├── components/                     ← Single Directory Components
│   ├── droopy-guide/
│   ├── skill-card/
│   ├── project-card/
│   ├── article-teaser/
│   ├── feature-badge/
│   ├── social-links/
│   └── theme-toggle/
├── scss/                           ← SCSS source files (compiled to css/)
│   ├── main.scss                   ← entry point, @use imports only
│   ├── abstracts/
│   │   ├── _tokens.scss
│   │   └── _mixins.scss
│   ├── base/
│   │   ├── _reset.scss
│   │   └── _typography.scss
│   └── layout/
│       ├── _main.scss
│       ├── _forms.scss
│       └── _project-full.scss
├── css/                            ← compiled output (referenced by libraries.yml)
├── js/
├── images/
└── templates/
    ├── content/
    │   ├── node--project--full.html.twig
    │   └── (teaser overrides per bundle)
    ├── navigation/
    │   └── menu-local-tasks.html.twig
    ├── block/
    └── page/
```

---

## Content Architecture

### Content Types

| Machine name     | Label         | Notes                                      |
|------------------|---------------|--------------------------------------------|
| `profile`        | Profile       | Singleton. Bio, photo, social links, CV.   |
| `skill`          | Skill         | Technical skill with level and category.   |
| `project`        | Project       | Portfolio project with paragraphs detail.  |
| `article`        | Article       | Blog / TIL posts.                          |
| `feature_demo`   | Feature Demo  | Documents a Drupal feature built in site.  |

### Taxonomy Vocabularies

| Machine name       | Label            |
|--------------------|------------------|
| `skill_category`   | Skill Category   |
| `technology_stack` | Technology Stack |
| `project_status`   | Project Status   |
| `article_tags`     | Article Tags     |

### Paragraph Types

| Machine name         | Label             |
|----------------------|-------------------|
| `code_snippet`       | Code Snippet      |
| `image_caption`      | Image + Caption   |
| `key_takeaway`       | Key Takeaway      |
| `external_reference` | External Reference|

---

## Modules

### Contrib (install in this order)

```
admin_toolbar
admin_toolbar_tools
gin                       # admin theme
paragraphs
field_group
pathauto
token
metatag
metatag_open_graph
simple_sitemap
redirect
search_api
search_api_db
facets
restui
serialization
entity_print
```

### Core modules to enable

```
rest
serialization
jsonapi
```

`basic_auth` is deliberately **not** installed: nothing uses it, and every
request carrying credentials is a login attempt. The core node REST resource
(`rest.resource.entity.node`) is GET-only with cookie auth and `method`
granularity — with `resource` granularity core exposes POST/PATCH/DELETE
routes regardless of the `methods` list.

---

## Pathauto Patterns

| Content Type | Pattern                        |
|--------------|--------------------------------|
| profile      | `/about` (profile is the front page; `/about` 301s to `/`) |
| skill        | `/skills/[node:title]`         |
| project      | `/projects/[node:title]`       |
| article      | `/articles/[node:title]`       |
| feature_demo | `/features/[node:title]`       |

---

## Disabled Sections

Projects, articles, feature demos and skills are disabled for now: their node
pages and listing views redirect (302) to `/`, driven by
`portfolio_core.settings` (`disabled_bundles`, `disabled_routes`) and
`DisabledSectionsSubscriber`. Content, JSON:API and REST stay available;
users with `bypass node access` are not redirected. Re-enable a section by
removing it from the config and running `ddev drush cim -y`.

---

## AI Assistant (Droopy)

Droopy (`components/droopy-guide/`) is the floating mascot that answers
questions about Ricardo via `POST /api/portfolio/ask` (`AskController`). How it
works end to end, and why: **`docs/studies/droopy-ai-guide.md`**. Rules and
gotchas for working on it:

- **Config:** `portfolio_core.assistant` — `enabled` (kill switch: hides the
  widget, endpoint returns 404), `providers` order, model ids, max tokens.
- **Keys:** never in config or git. `$settings['portfolio_assistant']['groq_api_key']`
  / `['gemini_api_key']`, else env vars `GROQ_API_KEY` / `GEMINI_API_KEY`.
  Locally put them in `.ddev/.env` (git-ignored) and run `ddev restart`.
- **Answers are text only** (`textContent`), never HTML. Page actions come only
  from whitelisted `[[scroll:…]]`, `[[cv]]`, `[[email]]` tags. The section list
  is defined once, in `AnswerParser::SECTIONS`, and reaches the JS as the
  component's `sections` prop.
- **Facts** come from the profile node and skills (`AssistantContextBuilder`),
  cached with the profile's tags plus `node_list:skill` and
  `taxonomy_term_list:skill_category`. A new fact source needs its cache tag.
- **Models:** Groq `openai/gpt-oss-120b` with `reasoning_effort: low`; Gemini
  `gemini-flash-lite-latest` with `thinkingConfig.thinkingLevel: low` (Gemini 3+
  rejects `thinkingBudget`; `gemini-2.5-flash` is closed to new accounts).
- **Limits:** 10 questions/minute and 60/day per IP plus 500/day site-wide
  (core flood; the site cap uses the identifier `site`), question ≤ 1000 chars
  (6000 in fit mode), history ≤ 6 turns, foreign `Origin` → 403.
  The Origin check compares hosts only and also accepts the first
  `X-Forwarded-Host` (ngrok rewrites `Host`).
- **Extra knowledge:** when Droopy says "I don't have information about…" for
  something true, add one fact per line to the profile's
  `field_assistant_notes` ("Extra knowledge for Droopy"). It feeds only the
  assistant prompt and is never rendered on the page or the PDF CV.
- Certifications live in `field_certifications` (link: title with year +
  verification URL); they feed the page, both CVs, Droopy and the Person JSON-LD
  `hasCredential`.
- Skills are ordered by category term weight, then skill level (highest
  first), then name.
- Availability facts live on the profile node (`field_available_from`,
  `field_work_mode`, `field_engagement_types`, `field_schedule_flexibility`).
- **Tests:** PHPUnit in `tests/src/Unit/Assistant/`; Playwright in
  `tests/e2e/droopy.spec.js` mocks the endpoint, so it never spends provider quota.
- Note: in this environment `drush cset` does not persist; change config via
  the UI, `drush php:eval`, or `config/sync` + `drush cim`.

### Tailored CV (private)

How it works end to end, and why: **`docs/studies/tailored-cv.md`**.

- `/admin/portfolio/tailored-cv` (`TailoredCvForm`, permission `use tailored cv`,
  menu: Content › Tailored CV): paste a job description → AI draft → edit →
  download `<Site-Name>-CV-<Company>.pdf`. Nothing is saved to content or
  logs; the job description and draft sit only in Drupal's temporary form cache
  (expires after 6 hours).
- `src/Cv/`: `CvTailoringPrompt` (facts with ids j1.b2 / n1 / c1), `CvTailor`
  (uses `LlmChain`, 1,200 tokens), `TailoringPlanValidator` (drops unknown ids
  and skills, clamps, normalises glyphs dompdf lacks, flags highlight skills
  missing from their source, drops highlights that repeat a printed bullet, flags
  summaries with pronouns or no years figure; skills come back ticked/unticked
  for the preview checkboxes), `TailoredCvContext` + `CvPdfGenerator` (renders the
  profile through Entity Print's `printHtml()` under the default front-end theme).
- Limits live in `CvTailor`: job description ≤ 6,000 chars and 20 drafts/hour
  per client (flood `portfolio_core.cv_tailor`), thrown as
  `TailoringLimitException` (`TOO_LONG` / `TOO_MANY`).
- Plan jobs carry the work-experience paragraph id; always find a job with
  `TailoringPlanValidator::locateJob()`, never by position.
- `CvPdfGenerator::generate()` returns a `CvPdf` (bytes + pages); more than one
  page holds the download back once with a warning ("Download anyway").
- `_portfolio_core_preprocess_profile_pdf()` swaps summary, skills, bullets and adds
  highlights only while a plan is set; `portfolio_core_node_build_defaults_alter()`
  disables the render cache for that render so public and tailored CVs never mix.
- Droopy shortcut: logged in with the permission, typing `/tailored-cv` shows a
  link to the tool (`tailor_url` prop + `user.permissions` cache context).

### Production checklist (before enabling Droopy or the tailored CV on a hosted site)
1. Set `GROQ_API_KEY` / `GEMINI_API_KEY` as host environment variables.
2. Set `REVERSE_PROXY_ADDRESSES` (comma-separated proxy IPs/CIDRs) on the host
   so `getClientIp()` returns the visitor's IP; otherwise every visitor shares
   one flood bucket (10/min, 60/day for the whole site). The local
   `settings.php` reads it and sets `reverse_proxy` /
   `reverse_proxy_addresses`; `settings.php` is git-ignored, so the host's
   copy needs the same block (Acquia's settings include configures this itself):
   ```php
   $reverse_proxy_addresses = array_filter(array_map('trim', explode(',', (string) getenv('REVERSE_PROXY_ADDRESSES'))));
   if ($reverse_proxy_addresses) {
     $settings['reverse_proxy'] = TRUE;
     $settings['reverse_proxy_addresses'] = $reverse_proxy_addresses;
   }
   ```
3. Verify the model ids in `portfolio_core.assistant` still exist.
4. Until keys exist, `enabled: true` shows Droopy but every answer is the
   "napping" message — set `enabled: false` if that is not wanted.

---

## Config Management

- Config sync directory: `config/sync/`
- Always export config after structural changes via UI or code.
- Never commit without exporting config first if anything structural changed.
- Config split is not needed for a single-environment portfolio site.

---

## Caching Notes

- Use `#cache` metadata on all custom render arrays.
- Tag caches when rendering content that depends on other entities.
- Use `CacheableResponseInterface` on custom REST/controller responses.
- Run `ddev drush cr` after any theme or library changes.

---

## REST API Endpoint Conventions

- Namespace all custom routes under `/api/portfolio/`
- Return JSON responses using `JsonResponse` or `CacheableJsonResponse`.
- Always implement `CacheableResponseInterface` on cacheable endpoints.
- Document each endpoint in the README "Feature Inventory".

---

## Structured Data / JSON-LD

The site emits schema.org JSON-LD on every page type where it adds value. All
JSON-LD is attached to `$*['#attached']['html_head']` — never printed directly
in a Twig template.

### Hooks and locations

| Hook | File | Fires on |
|------|------|----------|
| `hook_page_attachments` | `portfolio_core.module` | Individual node pages |
| `hook_views_post_render` | `portfolio_core.module` | Views listing pages |

### Schema types in use

| Page / context | Schema type |
|----------------|-------------|
| `profile` node | `Person` |
| `project` node | `SoftwareSourceCode` |
| `article` node | `TechArticle` |
| `/projects` listing | `ItemList` of `SoftwareSourceCode` |
| `/skills` listing | `ItemList` of `DefinedTerm` |

### Attachment key naming

Keys must be unique per page. Conventions:
- Node pages: `portfolio-json-ld` (single block, no suffix needed)
- Listing pages: `portfolio-json-ld-{view-slug}-list` — e.g. `portfolio-json-ld-projects-list`

### Rules for adding new JSON-LD

1. **Node pages:** Add a new `if ($node->bundle() === '...')` block inside
   `portfolio_core_page_attachments()`.
2. **Listing pages:** Add the view ID to the `in_array()` allowlist in
   `portfolio_core_views_post_render()`, then add a branch for the new item type.
3. Use `array_filter()` to omit null/empty fields — never emit empty properties.
4. Always pass `JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_AMP | JSON_HEX_TAG`
   to `json_encode()`. The HEX flags matter: Drupal HTML-escapes `&` in `html_tag`
   values (inside `<script>` entities are not decoded, so `&` would reach crawlers
   as `&amp;`), and `<`/`>` escapes stop content from closing the script tag.
5. URLs must be absolute: `$node->toUrl()->setAbsolute()->toString()`.
6. Validate with **https://validator.schema.org** — not Google's Rich Results Test,
   which only detects Google-renderable rich result types and will show "No items
   detected" for valid markup that doesn't map to a visual SERP feature.

---

## Testing

- Unit tests go in `web/modules/custom/portfolio_core/tests/src/Unit/`
- Run with: `ddev exec ./vendor/bin/phpunit -c web/core web/modules/custom/portfolio_core/`
  (`drupal/core-dev` is a dev dependency; `-c web/core` loads core's PHPUnit config).
- Coding standards (must pass before committing):
  ```bash
  ddev exec ./vendor/bin/phpcs --standard=Drupal,DrupalPractice \
    --extensions=php,module,inc,install,theme,yml --ignore=node_modules,*.css,*.js \
    web/modules/custom web/themes/custom/portfolio_theme
  ```
  `phpcbf` with the same arguments fixes most violations automatically.
- At minimum, write unit tests for any custom service or plugin logic.
- **End-to-end (Playwright):** `tests/e2e/` holds smoke tests for what a
  visitor sees: the profile page and its JSON-LD, the theme switch, the PDF CV,
  disabled-section redirects, the public APIs, Droopy (open/close, text-only
  answers, action whitelist, napping fallback, foreign-Origin 403) and the
  tailored CV (anonymous 403, the owner's page, the `/tailored-cv` shortcut).
  Every test runs on a desktop and a phone viewport.
  ```bash
  ddev start                 # the suite runs against https://portfolio.ddev.site
  npm install                # once, at the project root
  npx playwright test        # E2E_BASE_URL=... to target another site
  npx playwright show-report
  ```
  - Mock `/api/portfolio/ask` with `page.route()`; never call real providers
    from tests.
  - Droopy floats forever, so its tests set
    `contextOptions: { reducedMotion: 'reduce' }` to keep it clickable.
  - Tests that need the owner use the session saved by `owner.setup.js` (the
    `setup` project logs in once with `ddev drush user:login`, or
    `E2E_LOGIN_URL`). Never fetch a login link per test: Drupal one-time links
    stop working once the user logs in again, so parallel workers break each
    other's links.
  - `@playwright/test` is pinned to match the locally installed Chromium; on a
    new machine run `npx playwright install chromium`.
  - The suite is not in CI yet: CI has no Drupal site to test against.
- **CI:** `.github/workflows/ci.yml` runs `composer audit`, the unit tests and
  phpcs on every push to `main` and every pull request. It never deploys.
- **Before every commit:** unit tests and phpcs pass; Playwright passes when
  front-end or routing code changed.
- **Dependency updates:** keep core on the current minor
  (`--with "drupal/core:~11.3.0"` etc.) unless a security fix requires the next
  one; `drupal/admin_toolbar` needs core ≥ 11.4 to be re-resolved, so exclude it
  from `composer update` until core moves to 11.4.

---

## Deployment

- Not deployed yet. Hosting target: Acquia Cloud (free tier) or Platform.sh.
- CI (GitHub Actions) checks code only; there is no deploy pipeline yet.
- A deploy must run `drush updb -y && drush cim -y && drush cr`, and follow the
  production checklist in the AI Assistant section.

---

## When Building Features

Follow this sequence for every feature:

1. Spec: write or update `docs/specs/<date>-<topic>-design.md` (see the
   AI-Assisted Development Workflow above).
2. Build test-first in `portfolio_core` or `portfolio_theme`.
3. Export config: `ddev drush cex -y`.
4. Verify: unit tests, phpcs, `config:status` clean, Playwright, and a manual
   check at https://portfolio.ddev.site.
5. Commit: stage explicitly, use Conventional Commits format.
6. Document: update the README Feature Inventory, the spec's status and drift
   notes and, for showcase features, a study in `docs/studies/`.

---

## What NOT to Do

- Do not use `\Drupal::` static calls inside injectable classes.
- Do not use jQuery.
- Do not run Drush or Composer outside DDEV.
- Do not commit without exporting config first.
- Do not use `git add .` — always stage explicitly.
- Do not create multiple custom modules; use `portfolio_core` for everything.
- Do not hardcode strings that should be translatable — use `t()` or `TranslatableMarkup`.
- Do not skip `#cache` metadata on custom render elements.
