# Entity Print PDF CV — Implementation Plan (Phase A)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a downloadable PDF CV to the portfolio's `/about` page, generated from the `profile` node using Entity Print + dompdf.

**Architecture:** Install Entity Print + dompdf, register a `pdf` view mode for the `profile` content type, add a `_portfolio_core_preprocess_profile_pdf()` helper that mirrors the existing `_portfolio_core_preprocess_profile_full()` pattern, then render via a dedicated Twig template with print-safe CSS attached via `hook_entity_print_css_alter()`. A "Download CV" link on the profile page triggers the Entity Print route.

**Tech Stack:** Drupal 11, `drupal/entity_print`, `dompdf/dompdf`, PHP 8.3, Twig, plain CSS (dompdf has no CSS variable/flexbox support)

---

## File Map

| File | Action | Responsibility |
|------|--------|---------------|
| `composer.json` / `composer.lock` | updated | Entity Print + dompdf dependency |
| `config/sync/core.entity_view_mode.node.pdf.yml` | create | Registers `pdf` view mode for all node types |
| `config/sync/core.entity_view_display.node.profile.pdf.yml` | create | Hides all fields in pdf view mode (template handles rendering) |
| `config/sync/entity_print.settings.yml` | create | Sets dompdf as PDF engine |
| `web/modules/custom/portfolio_core/portfolio_core.module` | updated | Adds `pdf` case to hook_preprocess_node dispatch + helper function + css alter hook |
| `web/themes/custom/portfolio_theme/templates/content/node--profile--pdf.html.twig` | create | CV layout for PDF output |
| `web/themes/custom/portfolio_theme/css/layout/cv-pdf.css` | create | Print-safe CSS (no CSS vars, no flexbox/grid) |
| `web/themes/custom/portfolio_theme/templates/content/node--profile.html.twig` | updated | Adds "Download CV" button |
| `PROMPT.md` | updated | Feature Inventory entry |

---

## Task 1: Install Entity Print + dompdf

**Files:**
- Modify: `composer.json` / `composer.lock`

- [ ] **Step 1: Require packages**

```bash
ddev composer require drupal/entity_print dompdf/dompdf
```

Expected: Composer resolves and installs both packages without errors.

- [ ] **Step 2: Enable the module**

```bash
ddev drush en entity_print -y
```

Expected output includes `entity_print` enabled with no errors.

- [ ] **Step 3: Verify module is active**

```bash
ddev drush pml --filter=entity_print
```

Expected: shows `entity_print` with status `Enabled`.

- [ ] **Step 4: Commit**

```bash
git add composer.json composer.lock
git commit -m "chore: require entity_print and dompdf"
```

---

## Task 2: Configure dompdf as the PDF engine

**Files:**
- Create: `config/sync/entity_print.settings.yml`

- [ ] **Step 1: Set PDF engine via drush**

```bash
ddev drush config-set entity_print.settings pdf_engine dompdf -y
```

Expected: `[success] Configuration 'entity_print.settings' has been updated.`

- [ ] **Step 2: Export config**

```bash
ddev drush cex -y
```

Expected: `config/sync/entity_print.settings.yml` is created/updated.

- [ ] **Step 3: Verify the config file**

```bash
cat config/sync/entity_print.settings.yml
```

Expected: file contains `pdf_engine: dompdf`.

- [ ] **Step 4: Commit**

```bash
git add config/sync/entity_print.settings.yml
git commit -m "chore: configure entity_print to use dompdf engine"
```

---

## Task 3: Register the `pdf` view mode for profile

**Files:**
- Create: `config/sync/core.entity_view_mode.node.pdf.yml`
- Create: `config/sync/core.entity_view_display.node.profile.pdf.yml`

- [ ] **Step 1: Create the view mode config file**

Create `config/sync/core.entity_view_mode.node.pdf.yml`:

```yaml
langcode: en
status: true
dependencies:
  module:
    - node
id: node.pdf
label: PDF
targetEntityType: node
cache: true
```

- [ ] **Step 2: Create the view display config file**

Create `config/sync/core.entity_view_display.node.profile.pdf.yml`:

```yaml
langcode: en
status: true
dependencies:
  config:
    - core.entity_view_mode.node.pdf
    - node.type.profile
  module:
    - node
id: node.profile.pdf
targetEntityType: node
bundle: profile
mode: pdf
content: {}
hidden:
  field_bio: true
  field_cv_file: true
  field_drupalorg_url: true
  field_email: true
  field_github_url: true
  field_linkedin_url: true
  field_location: true
  field_photo: true
  field_subtitle: true
  field_years_experience: true
  links: true
```

> All fields are hidden because `node--profile--pdf.html.twig` renders via
> preprocessed variables — not Drupal field renderers.

- [ ] **Step 3: Import config**

```bash
ddev drush cim -y
```

Expected: no errors. The `pdf` view mode is now registered.

- [ ] **Step 4: Rebuild cache**

```bash
ddev drush cr
```

- [ ] **Step 5: Commit**

```bash
git add config/sync/core.entity_view_mode.node.pdf.yml
git add config/sync/core.entity_view_display.node.profile.pdf.yml
git commit -m "feat: register pdf view mode for profile content type"
```

---

## Task 4: Add pdf preprocess to portfolio_core.module

**Files:**
- Modify: `web/modules/custom/portfolio_core/portfolio_core.module`

- [ ] **Step 1: Add `pdf` to the view_mode dispatch**

In `portfolio_core_preprocess_node()`, after the `full` block (around line 30), add:

```php
  if ($view_mode === 'pdf') {
    match ($node->bundle()) {
      'profile' => _portfolio_core_preprocess_profile_pdf($variables, $node),
      default   => NULL,
    };
  }
```

The full function after the edit should look like:

```php
function portfolio_core_preprocess_node(array &$variables): void {
  /** @var \Drupal\node\NodeInterface $node */
  $node = $variables['node'];
  $view_mode = $variables['view_mode'];

  if ($view_mode === 'teaser') {
    match ($node->bundle()) {
      'project'      => _portfolio_core_preprocess_project_teaser($variables, $node),
      'article'      => _portfolio_core_preprocess_article_teaser($variables, $node),
      'feature_demo' => _portfolio_core_preprocess_feature_demo_teaser($variables, $node),
      'skill'        => _portfolio_core_preprocess_skill_teaser($variables, $node),
      default        => NULL,
    };
    return;
  }

  if ($view_mode === 'full') {
    match ($node->bundle()) {
      'project' => _portfolio_core_preprocess_project_full($variables, $node),
      'profile' => _portfolio_core_preprocess_profile_full($variables, $node),
      'article' => _portfolio_core_preprocess_article_full($variables, $node),
      default   => NULL,
    };
    return;
  }

  if ($view_mode === 'pdf') {
    match ($node->bundle()) {
      'profile' => _portfolio_core_preprocess_profile_pdf($variables, $node),
      default   => NULL,
    };
  }
}
```

- [ ] **Step 2: Add the helper function**

Append to `portfolio_core.module` after `_portfolio_core_preprocess_project_full()`:

```php
/**
 * Prepares variables for profile PDF template.
 */
function _portfolio_core_preprocess_profile_pdf(array &$variables, NodeInterface $node): void {
  $variables['profile_name'] = $node->label();
  $variables['profile_subtitle'] = $node->get('field_subtitle')->value ?? '';
  $variables['profile_location'] = $node->get('field_location')->value ?? '';
  $variables['profile_email'] = $node->get('field_email')->value ?? '';
  $variables['profile_years_experience'] = $node->get('field_years_experience')->value ?? '';

  $bio_field = $node->get('field_bio');
  $variables['profile_bio'] = !$bio_field->isEmpty()
    ? strip_tags(check_markup($bio_field->value, $bio_field->format))
    : '';

  $github = $node->get('field_github_url');
  $variables['profile_github_url'] = !$github->isEmpty()
    ? $github->first()->getUrl()->toString()
    : '';

  $linkedin = $node->get('field_linkedin_url');
  $variables['profile_linkedin_url'] = !$linkedin->isEmpty()
    ? $linkedin->first()->getUrl()->toString()
    : '';

  $drupalorg = $node->get('field_drupalorg_url');
  $variables['profile_drupalorg_url'] = !$drupalorg->isEmpty()
    ? $drupalorg->first()->getUrl()->toString()
    : '';

  $variables['#cache']['tags'] = $node->getCacheTags();
}
```

> `strip_tags()` wraps the rendered bio because dompdf renders HTML but
> complex markup from the body field can break the PDF layout. Plain text
> is safer for Phase A; Phase B can revisit with explicit allowed tags.

- [ ] **Step 3: Rebuild cache and confirm no PHP errors**

```bash
ddev drush cr
```

Expected: no errors in output.

- [ ] **Step 4: Commit**

```bash
git add web/modules/custom/portfolio_core/portfolio_core.module
git commit -m "feat: add _portfolio_core_preprocess_profile_pdf helper"
```

---

## Task 5: Create the PDF Twig template

**Files:**
- Create: `web/themes/custom/portfolio_theme/templates/content/node--profile--pdf.html.twig`

- [ ] **Step 1: Create the template**

Create `web/themes/custom/portfolio_theme/templates/content/node--profile--pdf.html.twig`:

```twig
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>{{ profile_name }} — CV</title>
</head>
<body>

  <div class="cv">

    <div class="cv__header">
      <h1 class="cv__name">{{ profile_name }}</h1>
      {% if profile_subtitle %}
        <p class="cv__subtitle">{{ profile_subtitle }}</p>
      {% endif %}
      <div class="cv__meta">
        {% if profile_location %}
          <span class="cv__meta-item">{{ profile_location }}</span>
        {% endif %}
        {% if profile_years_experience %}
          <span class="cv__meta-item">{{ profile_years_experience }} years experience</span>
        {% endif %}
      </div>
    </div>

    <div class="cv__contact">
      {% if profile_email %}
        <div class="cv__contact-item">
          <strong>Email:</strong> {{ profile_email }}
        </div>
      {% endif %}
      {% if profile_github_url %}
        <div class="cv__contact-item">
          <strong>GitHub:</strong> {{ profile_github_url }}
        </div>
      {% endif %}
      {% if profile_linkedin_url %}
        <div class="cv__contact-item">
          <strong>LinkedIn:</strong> {{ profile_linkedin_url }}
        </div>
      {% endif %}
      {% if profile_drupalorg_url %}
        <div class="cv__contact-item">
          <strong>Drupal.org:</strong> {{ profile_drupalorg_url }}
        </div>
      {% endif %}
    </div>

    {% if profile_bio %}
      <div class="cv__section">
        <h2 class="cv__section-title">About</h2>
        <p class="cv__bio">{{ profile_bio }}</p>
      </div>
    {% endif %}

  </div>

</body>
</html>
```

> The template includes `<html>`/`<head>`/`<body>` tags because Entity Print
> passes the rendered output directly to dompdf, which expects a full
> HTML document.

- [ ] **Step 2: Rebuild cache**

```bash
ddev drush cr
```

- [ ] **Step 3: Smoke-test the PDF route**

Find the profile node ID:

```bash
ddev drush eval "print_r(array_values(\Drupal::entityQuery('node')->condition('type','profile')->accessCheck(FALSE)->execute()));"
```

Expected output: `Array ( [0] => 1 )` — note the NID (e.g., `1`).

Then open in browser: `https://portfolio.ddev.site/printable/pdf/node/{NID}`

Expected: PDF downloads with name, subtitle, location, contact links, and bio visible.

- [ ] **Step 4: Commit**

```bash
git add web/themes/custom/portfolio_theme/templates/content/node--profile--pdf.html.twig
git commit -m "feat: add node--profile--pdf.html.twig CV template"
```

---

## Task 6: Add print-safe CSS for the PDF

**Files:**
- Create: `web/themes/custom/portfolio_theme/css/layout/cv-pdf.css`
- Modify: `web/modules/custom/portfolio_core/portfolio_core.module`

- [ ] **Step 1: Create the CSS file**

Create `web/themes/custom/portfolio_theme/css/layout/cv-pdf.css`:

```css
body {
  font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
  font-size: 11pt;
  color: #1a1a1a;
  margin: 0;
  padding: 0;
}

.cv {
  width: 100%;
  max-width: 700px;
  margin: 0 auto;
  padding: 24pt;
}

.cv__header {
  border-bottom: 2pt solid #1a1a1a;
  padding-bottom: 12pt;
  margin-bottom: 12pt;
}

.cv__name {
  font-size: 22pt;
  font-weight: 700;
  margin: 0 0 4pt 0;
  color: #1a1a1a;
}

.cv__subtitle {
  font-size: 13pt;
  color: #444444;
  margin: 0 0 6pt 0;
}

.cv__meta {
  font-size: 10pt;
  color: #666666;
}

.cv__meta-item {
  display: inline-block;
  margin-right: 16pt;
}

.cv__contact {
  margin-bottom: 16pt;
}

.cv__contact-item {
  font-size: 10pt;
  margin-bottom: 4pt;
}

.cv__section {
  margin-bottom: 16pt;
}

.cv__section-title {
  font-size: 13pt;
  font-weight: 700;
  color: #1a1a1a;
  border-bottom: 1pt solid #cccccc;
  padding-bottom: 4pt;
  margin: 0 0 8pt 0;
}

.cv__bio {
  font-size: 11pt;
  line-height: 1.5;
  color: #333333;
  margin: 0;
}
```

> Plain color values throughout — dompdf does not support CSS custom
> properties (`var(--color-primary)`). No flexbox or grid — dompdf
> uses its own box model. Display:inline-block is supported.

- [ ] **Step 2: Add hook_entity_print_css_alter()**

Append to `web/modules/custom/portfolio_core/portfolio_core.module`:

```php
/**
 * Implements hook_entity_print_css_alter().
 */
function portfolio_core_entity_print_css_alter(array &$css, \Drupal\Core\Entity\EntityInterface $entity): void {
  $theme_path = \Drupal::service('extension.list.theme')->getPath('portfolio_theme');
  $css[] = $theme_path . '/css/layout/cv-pdf.css';
}
```

- [ ] **Step 3: Rebuild cache**

```bash
ddev drush cr
```

- [ ] **Step 4: Re-test the PDF**

Open `https://portfolio.ddev.site/printable/pdf/node/{NID}` in browser.

Expected: PDF renders with correct typography — serif-free font, bordered header, section titles with underline.

- [ ] **Step 5: Commit**

```bash
git add web/themes/custom/portfolio_theme/css/layout/cv-pdf.css
git add web/modules/custom/portfolio_core/portfolio_core.module
git commit -m "feat: add cv-pdf.css and hook_entity_print_css_alter for PDF styling"
```

---

## Task 7: Add "Download CV" button to the profile page

**Files:**
- Modify: `web/themes/custom/portfolio_theme/templates/content/node--profile.html.twig`

- [ ] **Step 1: Add the download link**

In `node--profile.html.twig`, add the download button after the `</header>` closing tag and before `{% if profile_bio %}`:

```twig
  <div class="about__actions">
    <a href="{{ path('entity_print.view', {export_type: 'pdf', entity_type: 'node', entity_id: node.id}) }}"
       class="about__download-btn"
       target="_blank">
      Download CV (PDF)
    </a>
  </div>
```

The full updated file should look like:

```twig
<article class="about">

  <header class="about__header">
    {% if profile_subtitle %}
      <p class="about__subtitle">{{ profile_subtitle }}</p>
    {% endif %}
    <div class="about__meta">
      {% if profile_location %}
        <span class="about__meta-item">{{ profile_location }}</span>
      {% endif %}
      {% if profile_email %}
        <a href="mailto:{{ profile_email }}" class="about__meta-item about__meta-item--link">{{ profile_email }}</a>
      {% endif %}
      {% if profile_github_url %}
        <a href="{{ profile_github_url }}" class="about__meta-item about__meta-item--link" target="_blank" rel="noopener noreferrer">GitHub</a>
      {% endif %}
      {% if profile_linkedin_url %}
        <a href="{{ profile_linkedin_url }}" class="about__meta-item about__meta-item--link" target="_blank" rel="noopener noreferrer">LinkedIn</a>
      {% endif %}
      {% if profile_drupalorg_url %}
        <a href="{{ profile_drupalorg_url }}" class="about__meta-item about__meta-item--link" target="_blank" rel="noopener noreferrer">Drupal.org</a>
      {% endif %}
    </div>
  </header>

  <div class="about__actions">
    <a href="{{ path('entity_print.view', {export_type: 'pdf', entity_type: 'node', entity_id: node.id}) }}"
       class="about__download-btn"
       target="_blank">
      Download CV (PDF)
    </a>
  </div>

  {% if profile_bio %}
    <div class="about__bio">
      {{ profile_bio }}
    </div>
  {% endif %}

</article>
```

- [ ] **Step 2: Add button styles to the about SCSS**

Open `web/themes/custom/portfolio_theme/scss/layout/_about.scss` and append:

```scss
.about__actions {
  margin: var(--space-6) 0;
}

.about__download-btn {
  display: inline-block;
  padding: var(--space-2) var(--space-6);
  background: var(--color-highlight);
  color: var(--color-text);
  text-decoration: none;
  border-radius: var(--border-radius);
  font-size: var(--text-sm);
  font-weight: 600;
  transition: opacity var(--transition-fast);

  &:hover {
    opacity: 0.85;
  }
}
```

- [ ] **Step 3: Compile SCSS**

From the theme directory:

```bash
cd web/themes/custom/portfolio_theme && npm run build
```

Expected: `css/main.css` updated without errors.

- [ ] **Step 4: Rebuild Drupal cache**

```bash
ddev drush cr
```

- [ ] **Step 5: Verify in browser**

Open `https://portfolio.ddev.site/about`.

Expected: "Download CV (PDF)" button visible below the profile header. Clicking it opens/downloads the PDF.

- [ ] **Step 6: Commit**

```bash
git add web/themes/custom/portfolio_theme/templates/content/node--profile.html.twig
git add web/themes/custom/portfolio_theme/scss/layout/_about.scss
git add web/themes/custom/portfolio_theme/css/main.css
git commit -m "feat: add Download CV button to profile page"
```

---

## Task 8: Export config and update Feature Inventory

**Files:**
- Modify: `PROMPT.md`

- [ ] **Step 1: Final config export**

```bash
ddev drush cex -y
```

Check for any new config files generated by Entity Print during testing.

- [ ] **Step 2: Update Feature Inventory in PROMPT.md**

In the Feature Inventory table, append:

```markdown
| 11 | PDF CV export (Phase A) | Entity Print + dompdf + view mode + preprocess + css alter hook | — | ✅ |
```

- [ ] **Step 3: Commit everything**

```bash
git add config/sync/
git add PROMPT.md
git commit -m "feat: complete Entity Print PDF CV export (Phase A)"
```

---

## Verification Checklist

- [ ] `https://portfolio.ddev.site/about` shows "Download CV (PDF)" button
- [ ] Clicking the button downloads a PDF
- [ ] PDF contains: name, subtitle, location, years of experience, contact links, bio
- [ ] `ddev drush cr` completes without PHP errors
- [ ] All new config files are committed in `config/sync/`
- [ ] Feature Inventory updated in `PROMPT.md`
