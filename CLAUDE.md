# CLAUDE.md — Portfolio Project

## Project Overview

Personal portfolio site built on Drupal 11. The site serves two purposes:
1. Professional presentation: bio, projects, skills, articles.
2. Living technical showcase: every major feature is deliberately implemented
   using a different Drupal pattern so the codebase itself is the portfolio.

This file is the single source of truth for Claude Code. Read it completely
before taking any action on this project.

---

## Local Environment

- **Tool:** DDEV
- **PHP:** 8.3
- **Node:** 20
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
├── config/
│   └── sync/                       ← exported config lives here
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
├── PROMPT.md                       ← build specification
├── README.md
├── composer.json
└── composer.lock
```

---

## Git Workflow

- **Main branch:** `main` (always deployable)
- **Feature branches:** `feature/<short-name>` (e.g., `feature/skill-content-type`)
- **Fix branches:** `fix/<short-name>`

### Commit format (Conventional Commits)

```
feat: add Skill content type with taxonomy reference
fix: correct pathauto pattern for project nodes
docs: document REST endpoint in PROMPT.md
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
- Strict types declaration required on all PHP files:
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
├── src/
│   ├── Controller/
│   ├── Plugin/
│   │   ├── Block/
│   │   └── rest/
│   ├── TwigExtension/
│   └── Service/
└── tests/
    └── src/
        └── Unit/
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
hal
basic_auth
decoupled_router
```

### Core modules to enable

```
rest
serialization
basic_auth
jsonapi
contact
```

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
questions about Ricardo via `POST /api/portfolio/ask` (`AskController`).

- **Config:** `portfolio_core.assistant` — `enabled` (kill switch: hides the
  widget, endpoint returns 404), `providers` order, model ids, max tokens.
- **Keys:** never in config or git. `$settings['portfolio_assistant']['groq_api_key']`
  / `['gemini_api_key']`, else env vars `GROQ_API_KEY` / `GEMINI_API_KEY`.
  Locally put them in `.ddev/.env` (git-ignored) and run `ddev restart`.
- **Services** (`src/Assistant/`): `AssistantKeys`, `AssistantContextBuilder`
  (system prompt from profile facts, cached with profile tags),
  `GroqClient` / `GeminiClient` (`LlmClientInterface`), `LlmChain`
  (fallback; NULL → "napping" answer), `AnswerParser` (whitelisted
  `[[scroll:…]]`, `[[cv]]`, `[[email]]` action tags), `AskRequestValidator`.
- **Limits:** 10 questions/minute and 60/day per IP (core flood), question
  ≤ 1000 chars (6000 in fit mode), history ≤ 6 turns, foreign `Origin` → 403.
- Answers are rendered as text only (`textContent`), never HTML.
- Availability facts live on the profile node (`field_available_from`,
  `field_work_mode`, `field_engagement_types`, `field_schedule_flexibility`).
- Note: in this environment `drush cset` does not persist; change config via
  the UI, `drush php:eval`, or `config/sync` + `drush cim`.

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
- Document each endpoint in PROMPT.md under "Feature Inventory".

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
4. Always pass `JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE` to `json_encode()`.
5. URLs must be absolute: `$node->toUrl()->setAbsolute()->toString()`.
6. Validate with **https://validator.schema.org** — not Google's Rich Results Test,
   which only detects Google-renderable rich result types and will show "No items
   detected" for valid markup that doesn't map to a visual SERP feature.

---

## Testing

- Unit tests go in `web/modules/custom/portfolio_core/tests/src/Unit/`
- Run with: `ddev exec ./vendor/bin/phpunit web/modules/custom/portfolio_core/`
- At minimum, write unit tests for any custom service or plugin logic.

---

## Deployment

- Hosting target: Acquia Cloud (free tier) or Platform.sh.
- CI/CD: GitHub Actions.
- Deploy pipeline: push to `main` → GitHub Actions → platform deploy.
- Config import runs as part of deploy: `drush cim -y && drush cr`.

---

## When Building Features

Follow this sequence for every feature:

1. Plan: confirm approach against this file before writing code.
2. Build: implement in `portfolio_core` or `portfolio_theme` as appropriate.
3. Export config: `ddev drush cex -y`.
4. Test locally: verify at https://portfolio.ddev.site.
5. Commit: stage explicitly, use Conventional Commits format.
6. Document: update the "Feature Inventory" section in PROMPT.md.

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
