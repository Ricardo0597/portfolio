# CV PDF Pattern Refactor — Design Spec

**Date:** 2026-04-22
**Branch:** `feature/entity-print-cv-pdf`
**Status:** Approved

---

## Goal

Refactor the Entity Print PDF CV export to follow the CV Pattern Guide structure fully.
The current implementation renders only name, contact, and a bio. The target is a
complete, standards-compliant CV PDF covering all required sections plus Languages.

---

## Data Model

### New paragraph type: `work_experience`

Attached to the `profile` node via `field_work_experience` (entity_reference_revisions,
unlimited cardinality). Editors add and reorder jobs inline in the profile edit form.

| Field | Machine name | Type | Notes |
|---|---|---|---|
| Job Title | `field_we_job_title` | string | Required |
| Company | `field_we_company` | string | Required |
| Location | `field_we_location` | string | e.g. "Costa Rica" or "Remote" |
| Start Date | `field_we_start_date` | datetime | Required |
| End Date | `field_we_end_date` | datetime | Optional — empty = "Present" |
| Employment Type | `field_we_employment_type` | string | Optional (Full-time, Contract…) |
| Achievements | `field_we_achievements` | text_long | One bullet per line |

### New fields on `profile` node

**Education (single entry):**

| Field | Machine name | Type |
|---|---|---|
| Degree & Major | `field_edu_degree` | string |
| Institution | `field_edu_institution` | string |
| Location | `field_edu_location` | string |
| Start Year | `field_edu_start_year` | integer |
| End Year | `field_edu_end_year` | integer |

**Work experience reference:**

| Field | Machine name | Type |
|---|---|---|
| Work Experience | `field_work_experience` | entity_reference_revisions → `work_experience`, unlimited |

**Languages:**

| Field | Machine name | Type | Notes |
|---|---|---|---|
| Languages | `field_languages` | string, multi-value | e.g. `"Spanish — Native"` |

---

## Template Structure

`web/themes/custom/portfolio_theme/templates/content/node--profile--pdf.html.twig`

Section order follows the CV guide exactly. Each section only renders when data is present.

```
HEADER
  Full name (H1, 22pt bold)
  Subtitle / target role (13pt, accent colour)

CONTACT INFO
  Single inline row: City, Country · email · GitHub · LinkedIn · Drupal.org

PROFESSIONAL SUMMARY
  Bio field (plain text, 3–5 sentences)

CORE SKILLS
  Grouped by skill category, comma-separated within each group

WORK EXPERIENCE
  Per paragraph item (order is controlled by the editor in the profile form — editor must add jobs most-recent-first):
    Job Title — Company (bold)
    Location | MM/YYYY – MM/YYYY (or "Present")
    • Achievement bullet (one per line from field_we_achievements)

EDUCATION
  Degree — Institution
  Location | YYYY – YYYY

LANGUAGES
  One entry per field_languages value
```

---

## CSS Changes (`cv-pdf.css`)

Targets the `@page` rule and typography to meet the CV guide spec for tech/engineering roles.

| Rule | Current | Target |
|---|---|---|
| `@page` | not set | `size: A4; margin: 18mm` |
| `body` line-height | not set | `1.25` |
| `.cv__section-title` font-size | `13pt` | `11pt` |
| `.cv__section-title` text-transform | none | `uppercase` |
| `.cv__section-title` colour | `#1a1a1a` | `#1D6A72` (dark teal — tech accent) |
| `.cv__section-title` border | none | `0.5pt solid #cccccc` bottom |
| `.cv__header` border | `2pt solid #1a1a1a` | remove (divider moves to section titles) |

New CSS classes added:
- `.cv__contact-row` — inline contact line with `·` separator
- `.cv__work-item` — per-job container, `margin-bottom: 12pt`
- `.cv__work-header` — job title + company row
- `.cv__work-meta` — location + date range row, muted colour
- `.cv__achievements` — `<ul>` with tight bullet spacing
- `.cv__edu-item` — degree + institution row
- `.cv__skills-group` — per-category label + comma list
- `.cv__languages` — stacked language entries

---

## Preprocess Changes

`_portfolio_core_preprocess_profile_pdf` in `portfolio_core.module`:

### Skills query
```php
$skill_nodes = \Drupal::entityTypeManager()
  ->getStorage('node')
  ->loadByProperties(['type' => 'skill', 'status' => 1]);
```
Group by `field_category` term label. Sort alphabetically within each group.
Pass as `$variables['profile_skills']` — associative array keyed by category name,
values are arrays of skill labels.

### Work experience
Iterate `$node->get('field_work_experience')->referencedEntities()`.
Per paragraph:
- Extract all fields
- Format `field_we_start_date` → `MM/YYYY`
- Format `field_we_end_date` → `MM/YYYY`, empty → `"Present"`
- Split `field_we_achievements` on `\r\n` or `\n` → array of non-empty strings

Pass as `$variables['profile_work_experience']` — array of structured arrays.

### Education
Read directly from profile node fields. Pass as individual variables:
`profile_edu_degree`, `profile_edu_institution`, `profile_edu_location`,
`profile_edu_start_year`, `profile_edu_end_year`.

### Languages
Read `$node->get('field_languages')` — iterate values, collect strings.
Pass as `$variables['profile_languages']` — array of strings.

---

## Files Touched

| File | Change |
|---|---|
| `web/modules/custom/portfolio_core/portfolio_core.module` | Update `_portfolio_core_preprocess_profile_pdf` |
| `web/themes/custom/portfolio_theme/templates/content/node--profile--pdf.html.twig` | Full rewrite |
| `web/themes/custom/portfolio_theme/css/layout/cv-pdf.css` | Update rules, add new classes |
| `web/themes/custom/portfolio_theme/scss/layout/_cv-pdf.scss` | New SCSS source — compiled independently to `css/layout/cv-pdf.css`, not imported via `main.scss` |
| `config/sync/` | New paragraph type config, new profile fields, updated view display |

---

## Out of Scope

- Skills page / web view (no change)
- Profile full view (no change)
- Certifications, Projects, Education as content types
- Multi-degree education entries
- ATS optimisation (PDF is for human readers)
