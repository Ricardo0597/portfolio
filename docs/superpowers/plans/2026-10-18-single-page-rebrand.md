# Single-Page Rebrand Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Serve the profile node at `/` as a single anchored page with a sticky section nav and a clear light/dark switch, while projects, articles, features and skills pages are disabled by config.

**Architecture:** The front page becomes node 1 (profile). The hero moves from `page--front.html.twig` into `node--profile.html.twig`, so one template owns the whole page and `page--front` is deleted. A config-driven request subscriber in `portfolio_core` redirects disabled routes/bundles and `/about`. Two new SDCs (`theme-toggle`, `social-links`) and one shared header partial replace the duplicated header markup.

**Tech Stack:** Drupal 11, PHP 8.3, Twig + SDC, SCSS (dart-sass via `ddev theme-build`), vanilla JS Drupal behaviors, PHPUnit (drupal/core-dev).

**Spec:** `docs/superpowers/specs/2026-10-18-single-page-rebrand-design.md`

## Global Constraints

- Every rule in `CLAUDE.md` applies: `declare(strict_types=1)`, DI in classes (no `\Drupal::` in classes), PHPDoc, `t()`/`|t` for every UI string, `#cache` metadata, BEM, `var(--color-*)` tokens only, light-mode exceptions only in the `[data-theme='light']` block of `scss/layout/_main.scss`, no jQuery, `once()`.
- Commands run through DDEV (`ddev drush`, `ddev composer`, `ddev theme-build`).
- After any structural change: `ddev drush cex -y` and stage `config/sync/` files explicitly. Never `git add .`. No `Co-Authored-By:` trailer.
- Disabled config: `disabled_bundles: [project, article, feature_demo, skill]`, `disabled_routes: [view.portfolio_projects.page_1, view.portfolio_skills.page_1]`.
- Redirect codes: disabled bundle/route → **302** to `/`; profile node when not front page → **301** to `/`. Users with `bypass node access` are not redirected. JSON:API, `/api/portfolio/*`, entity_print and edit routes untouched.
- Section anchors and nav labels, in order: `#about` About · `#skills` Skills · `#experience` Experience · `#education` Education · `#contact` Contact; then a separated `Headless` → `/headless`.
- Hero CTAs: primary "Download CV (PDF)" (entity_print route), secondary "Contact" → `#contact`.
- Theme: two-state switch, OS preference on first visit, stored choice (localStorage + `theme` cookie) wins; dark stays the `:root` default.
- CV PDF (`node--profile--pdf.html.twig`) must render unchanged.

## Review Focus

1. **Internal page cache serving stale responses** — an anonymous `/projects` cached as 200 before deploy, or a cached redirect after re-enabling. Expect `drush cr` to clear the first and the redirect to carry the `config:portfolio_core.settings` tag. → Task 1 test `testRedirectCarriesConfigCacheTag`; Task 2 curl check after `cr`.
2. **Query strings / `_format` on node routes** — `/node/5?_format=json` must not redirect. → Task 1 test `testNonHtmlFormatIsIgnored`.
3. **Theme flash on load for a light-OS first-time visitor** — page must paint light immediately. → Task 6 Step "first-visit check".
4. **Anchor lands under the sticky header** (heading hidden). → Task 4 `scroll-margin-top` + Task 5 check.
5. **Mobile: nav drawer stays open after tapping a section link**, covering content. → Task 5 behavior closes the drawer; checked at 375px.

---

### Task 1: DisabledSectionsSubscriber (config-driven redirects)

**Files:**
- Create: `web/modules/custom/portfolio_core/src/EventSubscriber/DisabledSectionsSubscriber.php`
- Create: `web/modules/custom/portfolio_core/config/install/portfolio_core.settings.yml` (both lists empty)
- Create: `web/modules/custom/portfolio_core/config/schema/portfolio_core.schema.yml`
- Create: `config/sync/portfolio_core.settings.yml` (lists from Global Constraints)
- Modify: `web/modules/custom/portfolio_core/portfolio_core.services.yml`
- Modify: `composer.json`, `composer.lock` (dev dependency)
- Test: `web/modules/custom/portfolio_core/tests/src/Unit/DisabledSectionsSubscriberTest.php`

**Interfaces:**
- Produces: service `portfolio_core.disabled_sections_subscriber`; config object `portfolio_core.settings` with keys `disabled_bundles: string[]`, `disabled_routes: string[]`.
- Produces: `public function resolveStatus(string $route_name, ?string $node_bundle, bool $is_front, string $format, bool $can_bypass): ?int` — returns `301`, `302` or `NULL` (no redirect). Pure; reads only the injected config.

- [ ] **Step 1: Install PHPUnit tooling** — `ddev composer require --dev drupal/core-dev:^11` (no `vendor/bin/phpunit` exists yet). Verify `ddev exec ./vendor/bin/phpunit --version` prints a version.

- [ ] **Step 2: Write the failing tests** in `DisabledSectionsSubscriberTest` (extends `Drupal\Tests\UnitTestCase`; build the subscriber with `$this->getConfigFactoryStub(['portfolio_core.settings' => [...]])` and mocks for path matcher / current user):

```php
testDisabledBundleRedirects302:      resolveStatus('entity.node.canonical', 'project', FALSE, 'html', FALSE) === 302
testEnabledBundleNotRedirected:      resolveStatus('entity.node.canonical', 'page', FALSE, 'html', FALSE) === NULL
testDisabledRouteRedirects302:       resolveStatus('view.portfolio_projects.page_1', NULL, FALSE, 'html', FALSE) === 302
testProfileOffFrontRedirects301:     resolveStatus('entity.node.canonical', 'profile', FALSE, 'html', FALSE) === 301
testProfileOnFrontNotRedirected:     resolveStatus('entity.node.canonical', 'profile', TRUE, 'html', FALSE) === NULL
testBypassUserNotRedirectedOnNode:   resolveStatus('entity.node.canonical', 'article', FALSE, 'html', TRUE) === NULL
testBypassUserStillRedirectedOnProfileAlias: resolveStatus('entity.node.canonical', 'profile', FALSE, 'html', TRUE) === 301
testNonHtmlFormatIsIgnored:          resolveStatus('entity.node.canonical', 'project', FALSE, 'json', FALSE) === NULL
testEditRouteIgnored:                resolveStatus('entity.node.edit_form', 'project', FALSE, 'html', FALSE) === NULL
testEmptyConfigDisablesNothing:      with both lists [] → resolveStatus('entity.node.canonical', 'project', FALSE, 'html', FALSE) === NULL
testRedirectCarriesConfigCacheTag:   onRequest() for a disabled route sets a response whose getCacheableMetadata()->getCacheTags() contains 'config:portfolio_core.settings'
```

- [ ] **Step 3: Run tests to verify they fail**
Run: `ddev exec ./vendor/bin/phpunit -c web/core web/modules/custom/portfolio_core/`
Expected: FAIL — class `DisabledSectionsSubscriber` not found.

- [ ] **Step 4: Implement `DisabledSectionsSubscriber`** (`final`, implements `EventSubscriberInterface`). Constructor: `ConfigFactoryInterface $configFactory, PathMatcherInterface $pathMatcher, AccountInterface $currentUser`. Subscribes `KernelEvents::REQUEST => ['onRequest', 30]` (after the router at 32). `onRequest(RequestEvent $event): void` — main requests only; read route name and `node` from request attributes (`RouteObjectInterface::ROUTE_NAME`), call `resolveStatus()` with `$request->getRequestFormat()` and `$currentUser->hasPermission('bypass node access')`; on a status, set `LocalRedirectResponse(Url::fromRoute('<front>')->toString(), $status)` with `addCacheableDependency($config)`. Register in `services.yml` with `@config.factory`, `@path.matcher`, `@current_user`, tag `event_subscriber`. Schema: `portfolio_core.settings` mapping with two `sequence` of `string`.

- [ ] **Step 5: Run tests to verify they pass** — same command. Expected: 11 tests, all PASS.

- [ ] **Step 6: Import config** — write `config/sync/portfolio_core.settings.yml`, then `ddev drush cim -y && ddev drush cr`. Verify `ddev drush cget portfolio_core.settings` shows both lists.

- [ ] **Step 7: Commit**
```bash
git add composer.json composer.lock web/modules/custom/portfolio_core/src/EventSubscriber/DisabledSectionsSubscriber.php web/modules/custom/portfolio_core/config web/modules/custom/portfolio_core/portfolio_core.services.yml web/modules/custom/portfolio_core/tests/src/Unit/DisabledSectionsSubscriberTest.php config/sync/portfolio_core.settings.yml
git commit -m "feat: add config-driven redirects for disabled sections"
```

### Task 2: Front page, block and sitemap config

**Files:**
- Modify (via drush/UI, then `cex`): `config/sync/system.site.yml`, `config/sync/block.block.portfolio_theme_page_title.yml`, `config/sync/block.block.portfolio_theme_main_menu.yml`, `config/sync/simple_sitemap.bundle_settings.default.node.{project,article,feature_demo,skill}.yml`

**Interfaces:**
- Consumes: Task 1 subscriber (for `/about` → `/`).
- Produces: `/` routes to `entity.node.canonical` node 1; no page-title block on `<front>`; main menu block disabled (Task 3 renders its own nav, keeping `id="site-nav"`).

- [ ] **Step 1: Apply config**
  - `ddev drush cset system.site page.front /node/1 -y`
  - Page title block visibility: `request_path` plugin, `pages: '<front>'`, `negate: true` (block UI at `/admin/structure/block/manage/portfolio_theme_page_title`).
  - `ddev drush cset block.block.portfolio_theme_main_menu status false -y`
  - Sitemap: set `index: false` on the four bundle settings (`/admin/config/search/simplesitemap/entities/node`), then `ddev drush simple-sitemap:generate`.

- [ ] **Step 2: Verify with curl** (anonymous, after `ddev drush cr`):
```
curl -sI https://portfolio.ddev.site/            → 200
curl -sI https://portfolio.ddev.site/about       → 301, location: /
curl -sI https://portfolio.ddev.site/node/1      → 301, location: /
curl -sI https://portfolio.ddev.site/projects    → 302, location: /
curl -sI https://portfolio.ddev.site/skills      → 302, location: /
curl -sI https://portfolio.ddev.site/jsonapi/node/project → 200
curl -sI "https://portfolio.ddev.site/print/pdf/node/1"   → 200
curl -s  https://portfolio.ddev.site/sitemap.xml | grep -c "<loc>"  → 1
```
Also pick one project/article node alias from `ddev drush sqlq "SELECT alias FROM path_alias WHERE alias LIKE '/projects/%' LIMIT 1"` → 302. Logged in as admin, the same alias → 200.

- [ ] **Step 3: Export and commit**
```bash
ddev drush cex -y
git add config/sync/system.site.yml config/sync/block.block.portfolio_theme_page_title.yml config/sync/block.block.portfolio_theme_main_menu.yml config/sync/simple_sitemap.bundle_settings.default.node.project.yml config/sync/simple_sitemap.bundle_settings.default.node.article.yml config/sync/simple_sitemap.bundle_settings.default.node.feature_demo.yml config/sync/simple_sitemap.bundle_settings.default.node.skill.yml
git commit -m "chore: make profile the front page and disable section listings"
```
(Stage any other file `cex` reports as changed only after checking it belongs to this task.)

### Task 3: Shared header with section nav + social-links SDC

**Files:**
- Create: `web/themes/custom/portfolio_theme/templates/layout/_site-header.html.twig`
- Create: `web/themes/custom/portfolio_theme/components/social-links/social-links.{component.yml,twig,scss}`
- Modify: `web/themes/custom/portfolio_theme/templates/layout/page.html.twig` (include header partial)
- Delete: `web/themes/custom/portfolio_theme/templates/layout/page--front.html.twig`
- Modify: `web/themes/custom/portfolio_theme/portfolio_theme.theme` (nav items; drop front-page hero block)
- Modify: `web/themes/custom/portfolio_theme/package.json` (add `social-links` to `build`, `build:dev`, `watch`)
- Modify: `web/themes/custom/portfolio_theme/scss/layout/_main.scss` (nav `--separated` modifier)

**Interfaces:**
- Produces: page variable `section_nav: array<int, array{label: TranslatableMarkup, url: string, separated?: bool}>` — urls `/#about` … `/#contact`, then `/headless` with `separated: TRUE`.
- Produces: SDC `portfolio_theme:social-links`, props `{github: string, linkedin: string, drupalorg: string, size: 'sm'|'md'}`; renders only non-empty links, inline SVG icons with `fill="currentColor"`, each `<a>` has `aria-label` (`|t`) and `rel="noopener noreferrer" target="_blank"`.
- Header partial keeps: branding region `{{ page.header }}`, `nav-toggle` button (`aria-controls="site-nav"`), `<nav id="site-nav" class="site-nav">` rendering `section_nav` with the existing `site-nav__list/__item/__link` classes, and a placeholder slot where Task 6 embeds `theme-toggle` (keep the current button until Task 6).

- [ ] **Step 1: Implement** the preprocess array (labels via `t()`), the partial, the SDC, and remove the `isFrontPage()` hero block from `portfolio_theme_preprocess_page()` (the hero moves to the node template in Task 4). The nav array is static, so no extra cache contexts are needed.
- [ ] **Step 2: Build and verify** — `ddev theme-build && ddev drush cr`. On `/` and a non-front Drupal page (`/user/login`): header shows logo, 5 section links + separated Headless, burger at 375px opens the same list. No PHP/Twig errors in `ddev drush ws --count=10`.
- [ ] **Step 3: Commit**
```bash
git add web/themes/custom/portfolio_theme/templates/layout/_site-header.html.twig web/themes/custom/portfolio_theme/templates/layout/page.html.twig web/themes/custom/portfolio_theme/templates/layout/page--front.html.twig web/themes/custom/portfolio_theme/components/social-links web/themes/custom/portfolio_theme/portfolio_theme.theme web/themes/custom/portfolio_theme/package.json web/themes/custom/portfolio_theme/scss/layout/_main.scss web/themes/custom/portfolio_theme/css/main.css
git commit -m "feat: add section nav header and social-links component"
```

### Task 4: Single-page profile template (hero, sections, contact, layout)

**Files:**
- Modify: `web/themes/custom/portfolio_theme/templates/content/node--profile.html.twig`
- Modify: `web/modules/custom/portfolio_core/portfolio_core.module` (`_portfolio_core_preprocess_profile_full`)
- Modify: `web/themes/custom/portfolio_theme/scss/layout/_about.scss`, `scss/layout/_main.scss` (hero rules), `scss/abstracts/_tokens.scss` (one new token)

**Interfaces:**
- Consumes: `portfolio_theme:social-links` (Task 3).
- Produces (preprocess, added to `_portfolio_core_preprocess_profile_full`): `profile_hero_roles: string[]` = `['Drupal Applications', 'REST APIs', 'Headless Experiences', 'Clean, Fast Code']` (each `t()`), `profile_cv_url: string` = `Url::fromRoute('entity_print.view', ['export_type' => 'pdf', 'entity_type' => 'node', 'entity_id' => $node->id()])->toString()`.
- Produces token: `--content-max: 880px` (`$content-max`), used by every section and the hero text.
- Produces section ids `about`, `skills`, `experience`, `education`, `contact` on `<section>` elements, each `scroll-margin-top: var(--header-height)` (define `--header-height` in `_tokens.scss`, matching the rendered header height).

- [ ] **Step 1: Restructure the template** in this order: hero (greeting `Hi, I'm`, `<h1>` = `label`, typed roles reusing existing `.hero__*`/`js-typing` markup, subtitle, CTAs per Global Constraints, social-links `size: 'md'`) → `#about` (stats strip + bio) → `#skills` → `#experience` (timeline) → `#education` (education + languages) → `#contact`. Remove the old `about__meta` contact row and the separate `about__actions` button. Contact section: heading `Contact`, line `Open to remote Drupal work.` (`|t`), `mailto:` primary button with the email, phone (`tel:` link), location, social-links `size: 'sm'`. All strings `|t`.
- [ ] **Step 2: Layout SCSS** — one column `max-width: var(--content-max)` for hero text and all sections; timeline rail inside the column so Experience/Education headings align with Skills. Hero background stays full-bleed. Tokens only; `#fff` only on accent/highlight backgrounds.
- [ ] **Step 3: Build and verify** — `ddev theme-build && ddev drush cr`; on `/` in both themes at 1280px and 375px: all six blocks present in order, single left edge, Contact button jumps to `#contact` with heading visible below the header, stats counter and typing effect still animate. Schema check: view source contains one `application/ld+json` with `"@type":"Person"`. `curl -sI https://portfolio.ddev.site/print/pdf/node/1` → 200 and the PDF looks as before.
- [ ] **Step 4: Commit**
```bash
git add web/themes/custom/portfolio_theme/templates/content/node--profile.html.twig web/modules/custom/portfolio_core/portfolio_core.module web/themes/custom/portfolio_theme/scss/layout/_about.scss web/themes/custom/portfolio_theme/scss/layout/_main.scss web/themes/custom/portfolio_theme/scss/abstracts/_tokens.scss web/themes/custom/portfolio_theme/css/main.css
git commit -m "feat: render profile as single page with anchored sections and contact"
```

### Task 5: Scroll-spy and smooth scrolling

**Files:**
- Modify: `web/themes/custom/portfolio_theme/js/portfolio.js` (new `Drupal.behaviors.sectionNav`; small change to `mobileNav`)
- Modify: `web/themes/custom/portfolio_theme/scss/base/_reset.scss` (`html { scroll-behavior: smooth }` inside `@media (prefers-reduced-motion: no-preference)`)

**Interfaces:**
- Consumes: `.site-nav__link` with `href="/#<id>"` (Task 3), section ids (Task 4).
- Produces: active link gets class `site-nav__link--active` and `aria-current="true"`; all others lose both.

- [ ] **Step 1: Implement `sectionNav`** — `once('section-nav', '.site-nav', context)`; map links whose hash matches an element on the page; `IntersectionObserver` with `rootMargin: '-40% 0px -55% 0px'` marks the intersecting section's link active. No-op when no sections exist (non-front pages). In `mobileNav`, close the drawer (`aria-expanded="false"`, remove `is-open`) when a `.site-nav__link` is clicked.
- [ ] **Step 2: Verify** — on `/`, scrolling highlights each link in turn; clicking a link scrolls smoothly and lands with the heading visible; at 375px tapping a link closes the drawer; with OS reduced-motion on, the jump is instant. No console errors.
- [ ] **Step 3: Commit**
```bash
git add web/themes/custom/portfolio_theme/js/portfolio.js web/themes/custom/portfolio_theme/scss/base/_reset.scss web/themes/custom/portfolio_theme/css/main.css
git commit -m "feat: add scroll-spy and smooth scrolling to section nav"
```

### Task 6: Theme-toggle SDC (sun/moon switch, OS default)

**Files:**
- Create: `web/themes/custom/portfolio_theme/components/theme-toggle/theme-toggle.{component.yml,twig,scss}`
- Modify: `templates/layout/_site-header.html.twig` (embed component, remove old button)
- Modify: `templates/layout/html.html.twig` (inline head script)
- Modify: `js/portfolio.js` (rewrite `Drupal.behaviors.themeToggle`)
- Modify: `scss/layout/_main.scss` (delete `.theme-toggle` block and `@keyframes spin-once`)
- Modify: `package.json` (add `theme-toggle` to the three scripts)

**Interfaces:**
- Produces: SDC `portfolio_theme:theme-toggle`, no props. Markup: `<button type="button" class="theme-toggle" role="switch" aria-checked="true" aria-label="{{ 'Dark mode'|t }}" title="{{ 'Toggle light/dark mode'|t }}">` containing `.theme-toggle__track`, `.theme-toggle__thumb`, `.theme-toggle__icon--sun`, `.theme-toggle__icon--moon` (inline SVG, `stroke="currentColor"`). `aria-checked="true"` = dark.
- Head script contract (exact logic):
```js
try{var t=localStorage.getItem('theme');if(!t){var m=document.cookie.match(/(?:^|;)\s*theme=([^;]+)/);if(m)t=m[1];}
if(!t&&window.matchMedia('(prefers-color-scheme: light)').matches)t='light';
if(t==='light')document.documentElement.setAttribute('data-theme','light');}catch(e){}
```
- Behavior contract: click toggles `data-theme` on `<html>`, sets `aria-checked`, persists to localStorage + cookie (`theme=<v>;path=/;max-age=31536000;SameSite=Lax`, as today). While nothing is stored, a `matchMedia('(prefers-color-scheme: light)')` `change` listener follows the OS. Thumb slides via CSS transition on `[aria-checked]`, disabled under `prefers-reduced-motion`.

- [ ] **Step 1: Implement** the SDC, head script, behavior, and SCSS removal. Toggle sits last in the header row, right-aligned; at 375px it stays visible next to the burger. Colours: track `var(--color-surface)` + `var(--color-border)`, thumb `var(--color-highlight)` with icons inside it `#fff`, inactive icon `var(--color-text-muted)`.
- [ ] **Step 2: Build** — `ddev theme-build && ddev drush cr`.
- [ ] **Step 3: First-visit check** — in a fresh private window with OS/emulated `prefers-color-scheme: light` (Playwright `emulateMedia`), `/` paints light with no dark flash and the switch shows sun active; with `dark`, paints dark.
- [ ] **Step 4: Persistence and a11y check** — click → mode flips, reload keeps it, OS change no longer overrides; Tab reaches the switch, Space/Enter toggles, screen reader name "Dark mode" with on/off state. Both modes pass the CLAUDE.md light-mode self-check. Delete `.playwright-mcp/` files afterwards.
- [ ] **Step 5: Commit**
```bash
git add web/themes/custom/portfolio_theme/components/theme-toggle web/themes/custom/portfolio_theme/templates/layout/_site-header.html.twig web/themes/custom/portfolio_theme/templates/layout/html.html.twig web/themes/custom/portfolio_theme/js/portfolio.js web/themes/custom/portfolio_theme/scss/layout/_main.scss web/themes/custom/portfolio_theme/css/main.css web/themes/custom/portfolio_theme/package.json
git commit -m "feat: replace emoji theme button with sun/moon switch following OS preference"
```

### Task 7: Full verification and docs

**Files:**
- Modify: `PROMPT.md` (Feature Inventory: "Single-page profile", "Config-driven disabled sections (event subscriber)", "Theme switch SDC")
- Modify: `CLAUDE.md` (Pathauto table note: profile served at `/`, `/about` 301s; theme structure lists new components)

- [ ] **Step 1: Run everything in the spec's Verification list** — PHPUnit (`ddev exec ./vendor/bin/phpunit -c web/core web/modules/custom/portfolio_core/` all PASS), the Task 2 curl matrix, Task 4–6 browser checks in both themes at 1280px and 375px, `ddev drush cex -y` reports no pending changes, validator.schema.org shows `Person` on `/` (via `ddev share` URL).
- [ ] **Step 2: Update docs and commit**
```bash
git add PROMPT.md CLAUDE.md
git commit -m "docs: document single-page profile, disabled sections and theme switch"
```
