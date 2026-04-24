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
│   └── feature-badge/
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
| profile      | `/about`                       |
| skill        | `/skills/[node:title]`         |
| project      | `/projects/[node:title]`       |
| article      | `/articles/[node:title]`       |
| feature_demo | `/features/[node:title]`       |

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
