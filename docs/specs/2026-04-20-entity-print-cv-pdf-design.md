# Entity Print PDF CV — Design Spec

## Goal

Add a downloadable PDF CV to the portfolio site, generated from the `profile`
node using Entity Print + dompdf. This demonstrates a real-world Drupal
pattern (PDF generation from a content entity) and directly supports applying
for Entity Print consulting work on Upwork.

---

## Scope

### Phase A (this spec)
Profile node fields only — no external entity loading.

### Phase B (future)
Extend Phase A by loading featured `skill` nodes in the preprocess hook and
rendering a skills section in the PDF.

---

## Architecture

**Modules / libraries:**
- `drupal/entity_print` — provides the `/printable/pdf/node/{nid}` route and
  PDF rendering pipeline.
- `dompdf/dompdf` — pure-PHP PDF engine; no system binary required; compatible
  with DDEV, Acquia Cloud free tier, and Platform.sh.

**Data flow:**
```
/about (Profile node)
  → "Download CV" link
    → /printable/pdf/node/{nid}
      → Entity Print renders node in `pdf` view mode
        → hook_preprocess_node() prepares clean variables
          → node--profile--pdf.html.twig renders CV HTML
            → dompdf converts HTML to PDF → streamed to browser
```

---

## Phase A — Implementation

### 1. Dependencies

```bash
ddev composer require drupal/entity_print dompdf/dompdf
ddev drush en entity_print -y
```

Configure Entity Print to use dompdf at
`/admin/config/content/entity_print` → export config.

### 2. View mode

Register a `pdf` view mode for the `profile` content type via config
(`core.entity_view_mode.node.pdf.yml`). Configure
`core.entity_view_display.node.profile.pdf.yml` to show only the fields
needed in the PDF: title, `field_subtitle`, `field_bio`, `field_location`,
`field_email`, `field_github_url`, `field_linkedin_url`,
`field_years_experience`. Hide photo and CV file fields in this view mode
(handled in template directly if needed).

### 3. Preprocess hook

In `portfolio_core.module`, implement `hook_preprocess_node()`:

- Condition: `$variables['node']->bundle() === 'profile'` and
  `$variables['view_mode'] === 'pdf'`.
- Extract and pass as clean scalar variables:
  `name`, `subtitle`, `bio`, `location`, `email`, `github_url`,
  `linkedin_url`, `years_experience`.
- Add `#cache` tags: `$node->getCacheTags()`.

### 4. Twig template

`web/themes/custom/portfolio_theme/templates/content/node--profile--pdf.html.twig`

A clean, print-optimised single-column CV layout using inline CSS
(dompdf has limited external CSS support). Sections:
- Header: name, subtitle, location, contact links
- Summary / bio
- Years of experience

### 5. PDF CSS

A dedicated `scss/layout/_cv-pdf.scss` compiled to `css/layout/cv-pdf.css`,
attached via `hook_entity_print_css_alter()` in `portfolio_core.module`.
Uses print-safe values (pt units, no CSS variables, no flexbox/grid — dompdf
has limited CSS support; use tables or block layout).

### 6. Download link

Add a "Download CV" button to the profile node's default (full) view via the
existing `node--profile.html.twig` template (to be created or extended).
The link points to `/printable/pdf/node/{{ node.id }}`.

---

## Phase B — Extension point (future)

In the same `hook_preprocess_node()` block, after Phase A variables:

```php
$skill_nids = \Drupal::entityQuery('node')
  ->condition('type', 'skill')
  ->condition('status', 1)
  ->condition('field_featured', 1)
  ->sort('field_skill_level', 'DESC')
  ->execute();
// load and map to $variables['skills'] array
```

No architectural change. Template gains a skills section.

---

## Feature Inventory entry

| # | Feature | Pattern | Status |
|---|---------|---------|--------|
| 11 | PDF CV export (Phase A) | Entity Print + dompdf + view mode + preprocess | pending |
| 12 | PDF CV with skills (Phase B) | Entity cross-load in preprocess | pending |

---

## Files changed / created

| File | Action |
|------|--------|
| `composer.json` / `composer.lock` | updated |
| `config/sync/core.entity_view_mode.node.pdf.yml` | new |
| `config/sync/core.entity_view_display.node.profile.pdf.yml` | new |
| `config/sync/entity_print.settings.yml` | new |
| `web/modules/custom/portfolio_core/portfolio_core.module` | updated |
| `web/themes/custom/portfolio_theme/templates/content/node--profile--pdf.html.twig` | new |
| `web/themes/custom/portfolio_theme/templates/content/node--profile.html.twig` | new/updated |
| `web/themes/custom/portfolio_theme/scss/layout/_cv-pdf.scss` | new |
| `web/themes/custom/portfolio_theme/css/layout/cv-pdf.css` | new (compiled) |
| `README.md` | updated (Feature Inventory) |
