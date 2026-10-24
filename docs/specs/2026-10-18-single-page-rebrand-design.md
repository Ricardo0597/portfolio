# Single-Page Rebrand — Design

**Date:** 2026-10-18
**Branch:** `feature/about-capabilities`
**Status:** Draft, awaiting approval

## Intent

Turn the site into a one-page CV-style portfolio. Today the front page is a
thin hero (name + typing effect) and the real substance lives on `/about`.
Visitors — mostly recruiters and clients — should get everything in one
scroll from `/`: who Ricardo is, how to reach him, what he knows, where he
has worked, and a one-click CV download.

The owner's prototype (screenshot, 2026-10-18) is the reference layout:
hero → contact links → stats → bio → core skills → work experience →
education → languages. Changes agreed on top of it are listed below.

Second goal: make the light/dark toggle obvious. Today it is an unlabeled
emoji (🌙/☀️) floating mid-header.

## Goals

1. `/` renders the profile node as a single page with anchored sections.
2. `/about` permanently redirects (301) to `/`.
3. Projects, articles and feature demos are **disabled for now** — no
   public HTML pages, no nav links, not in the sitemap — but their content,
   config and APIs are kept so they can be re-enabled by changing config.
4. A sticky header with section links (scroll-spy highlights the current
   section) replaces the current main menu.
5. A clear theme switch: sun/moon toggle at the right end of the header,
   following the OS preference on first visit.
6. One consistent content width: hero and every section share the same
   left edge (the prototype's indent jump at Work Experience goes away).

## Non-goals

- **Visual identity refresh** (new palette, fonts, logo). Deferred to a
  follow-up spec. This work must keep all colours on tokens so that
  follow-up is a `_tokens.scss` change.
- Projects / articles / features as sections on the single page.
- Deleting any content type, field, view, or node.
- Changes to the Next.js headless app or the CV PDF layout.

## Anti-goals (failure even if it "works")

- Unpublishing or deleting nodes to hide them (loses content, breaks the
  headless app which reads JSON:API).
- Disabling by editing content entities (menu links, redirects) only —
  those are not in config and would not survive a deploy (`drush cim`).
- Duplicate content: the profile reachable at both `/` and `/about` with 200.
- A toggle that flashes the wrong theme on load.
- Any hardcoded colour that breaks light mode (see CLAUDE.md rules).

## Agreed changes to the prototype

| Prototype | Final |
|---|---|
| "About Me" outline button in hero | **Contact** button → scrolls to `#contact` |
| Hero = "Download CV (PDF)" primary | Kept as primary CTA |
| Contact row (location, phone, email, GitHub, LinkedIn, Drupal.org) under hero | Social links (GitHub, LinkedIn, Drupal.org) become icon links in the hero; location/phone/email move to a new **Contact** section at the bottom |
| No navigation | Sticky header: logo · section links · theme switch |
| Mixed widths/indents | One content column; hero aligned to it |
| Emoji toggle mid-header | Sun/moon switch, right-aligned |

Final section order and anchors:

| Section | Anchor | Source |
|---|---|---|
| Hero (greeting, name, typed roles, subtitle, CTAs, social icons) | `#top` | profile node |
| Stats strip | — (part of About) | `profile_stats` |
| About (bio) | `#about` | `field_bio` |
| Core Skills | `#skills` | `profile_skills` |
| Work Experience | `#experience` | `profile_work_experience` |
| Education & Languages | `#education` | existing vars |
| Contact | `#contact` | location, phone, email, socials |

Header nav labels: About · Skills · Experience · Education · Contact
(all `t()`-wrapped).

## How it works

### Front page = profile node

- `system.site:page.front` → `/node/1` (the only profile node).
- `page--front.html.twig` renders the hero, then `{{ page.content }}`,
  which now outputs the profile node via `node--profile.html.twig`.
- Hero variables come from `portfolio_theme_preprocess_page()` as today;
  `hero_about_path` is replaced by `hero_cv_url` (entity_print route) and
  the social URLs.
- Page title block: hide on `<front>` via block visibility (config) — the
  hero already provides the `<h1>`.
- JSON-LD `Person` keeps working because the front-page route is still
  `entity.node.canonical` for node 1. Its `url` already points to `<front>`.

### Disabling sections (config-driven, reversible)

A request event subscriber in `portfolio_core`
(`src/EventSubscriber/DisabledSectionsSubscriber.php`, DI, unit-tested),
driven by new config `portfolio_core.settings`:

```yaml
disabled_bundles: [project, article, feature_demo, skill]
disabled_routes:
  - view.portfolio_projects.page_1
  - view.portfolio_skills.page_1
```

Behaviour on HTML requests:

- `entity.node.canonical` for a node whose bundle is in `disabled_bundles`
  → **302** to `/` (temporary: "disabled for now").
- Route in `disabled_routes` → **302** to `/`.
- `entity.node.canonical` for a `profile` node when the request is not the
  front page (i.e. `/about`, `/node/1`) → **301** to `/`.
- Admin users with `bypass node access` are **not** redirected on node
  routes, so content can still be edited and previewed. Edit/admin routes
  (`entity.node.edit_form` etc.) are never touched.
- JSON:API, REST (`/api/portfolio/*`), and entity_print routes are never
  touched.

Re-enabling = empty the lists in config + `drush cim`.

Caching: the subscriber listens on `KernelEvents::REQUEST` after routing
(priority below the router's 32). Redirect responses must not be stored by
the internal page cache in a way that outlives a config change — return a
cacheable redirect carrying the `config:portfolio_core.settings` cache tag
(or a non-cacheable one). Deploy already runs `drush cr`, which clears
pages cached before this change.

Also (config):
- Main menu block `portfolio_theme_main_menu` disabled (status: false);
  the header nav is rendered by the theme instead.
- `simple_sitemap` bundle settings: `project`, `article`, `feature_demo`,
  `skill` → `index: false`. `profile` is already `index: false` and `/`
  is already a custom link at priority 1.0 — no change there.
- Skills listing `/skills` is redirected too: the Core Skills section
  replaces it.

### Header and section nav

- Nav items are built in `portfolio_theme_preprocess_page()` as an array of
  `{label, anchor}` and rendered in both page templates. On non-front
  pages (still reachable: admin, 404, headless) the links are `/#anchor`.
- Each section in `node--profile.html.twig` gets an `id` and
  `scroll-margin-top` equal to the sticky header height.
- New behavior `Drupal.behaviors.sectionNav` (in `portfolio.js`):
  IntersectionObserver sets `aria-current="true"` and the
  `site-nav__link--active` class on the link for the visible section.
  Smooth scroll via CSS `scroll-behavior: smooth`, disabled under
  `prefers-reduced-motion`.
- Existing mobile burger (`nav-toggle` + `mobileNav` behavior) is reused;
  tapping a link closes the menu.
- The "Headless" link stays in the header as the last item, visually
  separated (it is a separate showcase app, not a section).

### Theme switch

- New SDC `components/theme-toggle/` (`theme-toggle.component.yml`,
  `.twig`, `.scss`), embedded in both page templates in place of the
  current `<button class="theme-toggle">`.
- Markup: `<button role="switch" aria-checked="…" aria-label="Dark mode">`
  containing a track with inline sun and moon SVGs (`currentColor`) and a
  sliding thumb. The icon for the active mode is highlighted. Visible tooltip
  via `title`.
- Position: last item in the header row, right-aligned.
- First-visit default: OS preference (`prefers-color-scheme`). Once the
  user clicks, their choice is stored (localStorage + cookie, as today) and
  wins over the OS setting.
- Inline `<head>` script in `html.html.twig` is updated: stored choice →
  else `matchMedia('(prefers-color-scheme: light)')` → set
  `data-theme="light"`. No flash.
- `themeToggle` behavior rewritten: toggles `data-theme`, updates
  `aria-checked`, persists. The spin animation is removed; the thumb slides
  (transition disabled under reduced motion). While no choice is stored, it
  also follows live OS changes.
- Dark remains the token default (`:root`); only how the initial mode is
  picked changes.

### Layout

- One content column: `max-width` ~ 880px (exact value is the builder's
  call), centered within `$container-max`. Hero text aligns to the same
  left edge.
- Timeline (Work Experience) keeps its rail but the rail sits inside the
  column, so headings line up with Core Skills.
- New Contact section: heading, short line ("Open to remote Drupal
  work" — editable string via `t()`), email as primary button (mailto),
  phone, location, social icons. Uses tokens only.
- Social icons: inline SVG with `currentColor` — new SDC
  `components/social-links/` reused in hero and Contact.

## Constraints

- Everything in CLAUDE.md applies: DI in classes, strict types, `t()`,
  `#cache` metadata, BEM, tokens only, light-mode exceptions only in
  `_main.scss`, no jQuery, `once()`.
- Config exported after each structural change; staged explicitly.
- Must work at phone width; header collapses to burger + toggle.
- The CV PDF (`node--profile--pdf.html.twig`) must render unchanged.

## Decisions made by Claude (owner to check)

1. **Theme switch is two-state (light/dark) following the OS on first
   visit**, not a three-way Light/Dark/System picker — simpler and more
   intuitive for a portfolio.
2. **`/skills` and skill node pages are disabled too**, since Core Skills
   on the page covers them.
3. **"Headless" stays in the header** as a separate link.
4. **Disabled pages 302 to `/`** rather than 404, so old links and search
   results land somewhere useful.
5. **Admins bypass the redirects** so content stays editable/previewable.
6. **Contact section uses mailto**, not the core contact form.
7. **Visual identity refresh is a separate follow-up.**

## Left to the builder

- Exact column width, spacing, icon artwork, animation timings.
- Whether nav items live in preprocess or a small helper; whether
  the subscriber reads config directly or via a small value object.
- Test cases beyond the redirect rules above.

## Verification

- `/` shows all sections; `/about` and `/node/1` → 301 `/`.
- `/projects`, `/skills`, any project/article/feature node → 302 `/`
  (anonymous); 200 for admin.
- `/jsonapi/node/project`, `/api/portfolio/projects`, CV PDF → 200.
- `sitemap.xml` lists only `/`.
- Theme: first visit follows OS; click persists across reload; no flash;
  keyboard operable; both modes pass the CLAUDE.md light-mode self-check.
- Scroll-spy highlights the right link; anchors land below sticky header.
- PHPUnit passes, including the new subscriber test.
- Schema validator: `Person` on `/`.
