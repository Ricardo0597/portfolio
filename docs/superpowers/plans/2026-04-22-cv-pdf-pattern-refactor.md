# CV PDF Pattern Refactor — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Refactor the Entity Print PDF CV export so it follows the CV Pattern Guide fully — adding Work Experience (paragraph type), Education fields, and Languages field to the profile, then wiring them all into the template and CSS.

**Architecture:** A new `work_experience` paragraph type is attached to the `profile` node. Five education fields and a multi-value languages field are added directly to the profile. The preprocess hook queries skill nodes and iterates work experience paragraphs; the Twig template renders all CV sections in spec order; the SCSS source is refactored to meet the CV guide's typography and page requirements.

**Tech Stack:** Drupal 11, PHP 8.3, Twig, SCSS, Entity Print, Paragraphs module, DDEV

---

## File Map

| File | Action | Responsibility |
|---|---|---|
| `config/sync/paragraphs.paragraphs_type.work_experience.yml` | Create | Paragraph type definition |
| `config/sync/field.storage.paragraph.field_we_job_title.yml` | Create | WE job title field storage |
| `config/sync/field.storage.paragraph.field_we_company.yml` | Create | WE company field storage |
| `config/sync/field.storage.paragraph.field_we_location.yml` | Create | WE location field storage |
| `config/sync/field.storage.paragraph.field_we_start_date.yml` | Create | WE start date field storage |
| `config/sync/field.storage.paragraph.field_we_end_date.yml` | Create | WE end date field storage |
| `config/sync/field.storage.paragraph.field_we_employment_type.yml` | Create | WE employment type field storage |
| `config/sync/field.storage.paragraph.field_we_achievements.yml` | Create | WE achievements field storage |
| `config/sync/field.field.paragraph.work_experience.field_we_*.yml` | Create (7 files) | WE field instances |
| `config/sync/core.entity_form_display.paragraph.work_experience.default.yml` | Create | WE paragraph form display |
| `config/sync/core.entity_view_display.paragraph.work_experience.default.yml` | Create | WE paragraph view display |
| `config/sync/field.storage.node.field_work_experience.yml` | Create | Profile→WE reference field storage |
| `config/sync/field.field.node.profile.field_work_experience.yml` | Create | Profile→WE field instance |
| `config/sync/field.storage.node.field_edu_degree.yml` | Create | Education degree field storage |
| `config/sync/field.storage.node.field_edu_institution.yml` | Create | Education institution field storage |
| `config/sync/field.storage.node.field_edu_location.yml` | Create | Education location field storage |
| `config/sync/field.storage.node.field_edu_start_year.yml` | Create | Education start year field storage |
| `config/sync/field.storage.node.field_edu_end_year.yml` | Create | Education end year field storage |
| `config/sync/field.field.node.profile.field_edu_*.yml` | Create (5 files) | Education field instances |
| `config/sync/field.storage.node.field_languages.yml` | Create | Languages field storage |
| `config/sync/field.field.node.profile.field_languages.yml` | Create | Languages field instance |
| `config/sync/core.entity_form_display.node.profile.default.yml` | Modify | Expose new profile fields in admin form |
| `config/sync/core.entity_view_display.node.profile.pdf.yml` | Modify | Mark new fields as hidden (template drives rendering) |
| `web/modules/custom/portfolio_core/portfolio_core.module` | Modify | Update `_portfolio_core_preprocess_profile_pdf` |
| `web/themes/custom/portfolio_theme/templates/content/node--profile--pdf.html.twig` | Modify | Full CV section structure |
| `web/themes/custom/portfolio_theme/scss/layout/cv-pdf.scss` | Create | SCSS source for PDF styles (no underscore — standalone compile target) |
| `web/themes/custom/portfolio_theme/css/layout/cv-pdf.css` | Modify | Compiled output (run `ddev theme-build`) |

---

## Task 1: Create `work_experience` paragraph type config

**Files:**
- Create: `config/sync/paragraphs.paragraphs_type.work_experience.yml`
- Create: `config/sync/field.storage.paragraph.field_we_job_title.yml`
- Create: `config/sync/field.storage.paragraph.field_we_company.yml`
- Create: `config/sync/field.storage.paragraph.field_we_location.yml`
- Create: `config/sync/field.storage.paragraph.field_we_start_date.yml`
- Create: `config/sync/field.storage.paragraph.field_we_end_date.yml`
- Create: `config/sync/field.storage.paragraph.field_we_employment_type.yml`
- Create: `config/sync/field.storage.paragraph.field_we_achievements.yml`

- [ ] **Step 1: Write paragraph type definition**

```yaml
# config/sync/paragraphs.paragraphs_type.work_experience.yml
uuid: a1b2c3d4-e5f6-7890-abcd-ef1234567890
langcode: en
status: true
dependencies: {}
id: work_experience
label: 'Work Experience'
icon_uuid: null
icon_default: null
description: null
behavior_plugins: {}
```

- [ ] **Step 2: Write field storage files**

```yaml
# config/sync/field.storage.paragraph.field_we_job_title.yml
uuid: b2c3d4e5-f6a7-8901-bcde-f12345678901
langcode: en
status: true
dependencies:
  module:
    - paragraphs
id: paragraph.field_we_job_title
field_name: field_we_job_title
entity_type: paragraph
type: string
settings:
  max_length: 255
  case_sensitive: false
  is_ascii: false
module: core
locked: false
cardinality: 1
translatable: true
indexes: {}
persist_with_no_fields: false
custom_storage: false
```

```yaml
# config/sync/field.storage.paragraph.field_we_company.yml
uuid: c3d4e5f6-a7b8-9012-cdef-123456789012
langcode: en
status: true
dependencies:
  module:
    - paragraphs
id: paragraph.field_we_company
field_name: field_we_company
entity_type: paragraph
type: string
settings:
  max_length: 255
  case_sensitive: false
  is_ascii: false
module: core
locked: false
cardinality: 1
translatable: true
indexes: {}
persist_with_no_fields: false
custom_storage: false
```

```yaml
# config/sync/field.storage.paragraph.field_we_location.yml
uuid: d4e5f6a7-b8c9-0123-defa-234567890123
langcode: en
status: true
dependencies:
  module:
    - paragraphs
id: paragraph.field_we_location
field_name: field_we_location
entity_type: paragraph
type: string
settings:
  max_length: 255
  case_sensitive: false
  is_ascii: false
module: core
locked: false
cardinality: 1
translatable: true
indexes: {}
persist_with_no_fields: false
custom_storage: false
```

```yaml
# config/sync/field.storage.paragraph.field_we_start_date.yml
uuid: e5f6a7b8-c9d0-1234-efab-345678901234
langcode: en
status: true
dependencies:
  module:
    - datetime
    - paragraphs
id: paragraph.field_we_start_date
field_name: field_we_start_date
entity_type: paragraph
type: datetime
settings:
  datetime_type: date
module: datetime
locked: false
cardinality: 1
translatable: true
indexes: {}
persist_with_no_fields: false
custom_storage: false
```

```yaml
# config/sync/field.storage.paragraph.field_we_end_date.yml
uuid: f6a7b8c9-d0e1-2345-fabc-456789012345
langcode: en
status: true
dependencies:
  module:
    - datetime
    - paragraphs
id: paragraph.field_we_end_date
field_name: field_we_end_date
entity_type: paragraph
type: datetime
settings:
  datetime_type: date
module: datetime
locked: false
cardinality: 1
translatable: true
indexes: {}
persist_with_no_fields: false
custom_storage: false
```

```yaml
# config/sync/field.storage.paragraph.field_we_employment_type.yml
uuid: a7b8c9d0-e1f2-3456-abcd-567890123456
langcode: en
status: true
dependencies:
  module:
    - paragraphs
id: paragraph.field_we_employment_type
field_name: field_we_employment_type
entity_type: paragraph
type: string
settings:
  max_length: 255
  case_sensitive: false
  is_ascii: false
module: core
locked: false
cardinality: 1
translatable: true
indexes: {}
persist_with_no_fields: false
custom_storage: false
```

```yaml
# config/sync/field.storage.paragraph.field_we_achievements.yml
uuid: b8c9d0e1-f2a3-4567-bcde-678901234567
langcode: en
status: true
dependencies:
  module:
    - paragraphs
id: paragraph.field_we_achievements
field_name: field_we_achievements
entity_type: paragraph
type: text_long
settings: {}
module: text
locked: false
cardinality: 1
translatable: true
indexes: {}
persist_with_no_fields: false
custom_storage: false
```

- [ ] **Step 3: Import config and verify paragraph type exists**

```bash
ddev drush cim -y
ddev drush php-eval "echo \Drupal::entityTypeManager()->getStorage('paragraphs_type')->load('work_experience') ? 'OK' : 'MISSING';"
```

Expected: `OK`

- [ ] **Step 4: Commit**

```bash
git add config/sync/paragraphs.paragraphs_type.work_experience.yml \
  config/sync/field.storage.paragraph.field_we_job_title.yml \
  config/sync/field.storage.paragraph.field_we_company.yml \
  config/sync/field.storage.paragraph.field_we_location.yml \
  config/sync/field.storage.paragraph.field_we_start_date.yml \
  config/sync/field.storage.paragraph.field_we_end_date.yml \
  config/sync/field.storage.paragraph.field_we_employment_type.yml \
  config/sync/field.storage.paragraph.field_we_achievements.yml
git commit -m "feat: add work_experience paragraph type and field storages"
```

---

## Task 2: Create `work_experience` paragraph field instances and displays

**Files:**
- Create: `config/sync/field.field.paragraph.work_experience.field_we_job_title.yml`
- Create: `config/sync/field.field.paragraph.work_experience.field_we_company.yml`
- Create: `config/sync/field.field.paragraph.work_experience.field_we_location.yml`
- Create: `config/sync/field.field.paragraph.work_experience.field_we_start_date.yml`
- Create: `config/sync/field.field.paragraph.work_experience.field_we_end_date.yml`
- Create: `config/sync/field.field.paragraph.work_experience.field_we_employment_type.yml`
- Create: `config/sync/field.field.paragraph.work_experience.field_we_achievements.yml`
- Create: `config/sync/core.entity_form_display.paragraph.work_experience.default.yml`
- Create: `config/sync/core.entity_view_display.paragraph.work_experience.default.yml`

- [ ] **Step 1: Write field instance files**

```yaml
# config/sync/field.field.paragraph.work_experience.field_we_job_title.yml
uuid: c9d0e1f2-a3b4-5678-cdef-789012345678
langcode: en
status: true
dependencies:
  config:
    - field.storage.paragraph.field_we_job_title
    - paragraphs.paragraphs_type.work_experience
id: paragraph.work_experience.field_we_job_title
field_name: field_we_job_title
entity_type: paragraph
bundle: work_experience
label: 'Job Title'
description: ''
required: true
translatable: true
default_value: {}
default_value_callback: ''
settings: {}
field_type: string
```

```yaml
# config/sync/field.field.paragraph.work_experience.field_we_company.yml
uuid: d0e1f2a3-b4c5-6789-defa-890123456789
langcode: en
status: true
dependencies:
  config:
    - field.storage.paragraph.field_we_company
    - paragraphs.paragraphs_type.work_experience
id: paragraph.work_experience.field_we_company
field_name: field_we_company
entity_type: paragraph
bundle: work_experience
label: Company
description: ''
required: true
translatable: true
default_value: {}
default_value_callback: ''
settings: {}
field_type: string
```

```yaml
# config/sync/field.field.paragraph.work_experience.field_we_location.yml
uuid: e1f2a3b4-c5d6-7890-efab-901234567890
langcode: en
status: true
dependencies:
  config:
    - field.storage.paragraph.field_we_location
    - paragraphs.paragraphs_type.work_experience
id: paragraph.work_experience.field_we_location
field_name: field_we_location
entity_type: paragraph
bundle: work_experience
label: Location
description: 'City, Country or "Remote"'
required: false
translatable: true
default_value: {}
default_value_callback: ''
settings: {}
field_type: string
```

```yaml
# config/sync/field.field.paragraph.work_experience.field_we_start_date.yml
uuid: f2a3b4c5-d6e7-8901-fabc-012345678901
langcode: en
status: true
dependencies:
  config:
    - field.storage.paragraph.field_we_start_date
    - paragraphs.paragraphs_type.work_experience
  module:
    - datetime
id: paragraph.work_experience.field_we_start_date
field_name: field_we_start_date
entity_type: paragraph
bundle: work_experience
label: 'Start Date'
description: ''
required: true
translatable: true
default_value: {}
default_value_callback: ''
settings: {}
field_type: datetime
```

```yaml
# config/sync/field.field.paragraph.work_experience.field_we_end_date.yml
uuid: a3b4c5d6-e7f8-9012-abcd-123456789012
langcode: en
status: true
dependencies:
  config:
    - field.storage.paragraph.field_we_end_date
    - paragraphs.paragraphs_type.work_experience
  module:
    - datetime
id: paragraph.work_experience.field_we_end_date
field_name: field_we_end_date
entity_type: paragraph
bundle: work_experience
label: 'End Date'
description: 'Leave empty for current/present position.'
required: false
translatable: true
default_value: {}
default_value_callback: ''
settings: {}
field_type: datetime
```

```yaml
# config/sync/field.field.paragraph.work_experience.field_we_employment_type.yml
uuid: b4c5d6e7-f8a9-0123-bcde-234567890123
langcode: en
status: true
dependencies:
  config:
    - field.storage.paragraph.field_we_employment_type
    - paragraphs.paragraphs_type.work_experience
id: paragraph.work_experience.field_we_employment_type
field_name: field_we_employment_type
entity_type: paragraph
bundle: work_experience
label: 'Employment Type'
description: 'e.g. Full-time, Contract, Freelance'
required: false
translatable: true
default_value: {}
default_value_callback: ''
settings: {}
field_type: string
```

```yaml
# config/sync/field.field.paragraph.work_experience.field_we_achievements.yml
uuid: c5d6e7f8-a9b0-1234-cdef-345678901234
langcode: en
status: true
dependencies:
  config:
    - field.storage.paragraph.field_we_achievements
    - paragraphs.paragraphs_type.work_experience
  module:
    - text
id: paragraph.work_experience.field_we_achievements
field_name: field_we_achievements
entity_type: paragraph
bundle: work_experience
label: Achievements
description: 'One achievement per line. These render as bullet points in the CV.'
required: false
translatable: true
default_value: {}
default_value_callback: ''
settings:
  allowed_formats: {}
field_type: text_long
```

- [ ] **Step 2: Write form display**

```yaml
# config/sync/core.entity_form_display.paragraph.work_experience.default.yml
uuid: d6e7f8a9-b0c1-2345-defa-456789012345
langcode: en
status: true
dependencies:
  config:
    - field.field.paragraph.work_experience.field_we_job_title
    - field.field.paragraph.work_experience.field_we_company
    - field.field.paragraph.work_experience.field_we_location
    - field.field.paragraph.work_experience.field_we_start_date
    - field.field.paragraph.work_experience.field_we_end_date
    - field.field.paragraph.work_experience.field_we_employment_type
    - field.field.paragraph.work_experience.field_we_achievements
    - paragraphs.paragraphs_type.work_experience
  module:
    - datetime
    - text
id: paragraph.work_experience.default
targetEntityType: paragraph
bundle: work_experience
mode: default
content:
  field_we_job_title:
    type: string_textfield
    weight: 1
    region: content
    settings:
      size: 60
      placeholder: 'e.g. Senior Drupal Developer'
    third_party_settings: {}
  field_we_company:
    type: string_textfield
    weight: 2
    region: content
    settings:
      size: 60
      placeholder: 'Company name'
    third_party_settings: {}
  field_we_employment_type:
    type: string_textfield
    weight: 3
    region: content
    settings:
      size: 60
      placeholder: 'e.g. Full-time, Contract'
    third_party_settings: {}
  field_we_location:
    type: string_textfield
    weight: 4
    region: content
    settings:
      size: 60
      placeholder: 'City, Country or Remote'
    third_party_settings: {}
  field_we_start_date:
    type: datetime_default
    weight: 5
    region: content
    settings: {}
    third_party_settings: {}
  field_we_end_date:
    type: datetime_default
    weight: 6
    region: content
    settings: {}
    third_party_settings: {}
  field_we_achievements:
    type: text_textarea
    weight: 7
    region: content
    settings:
      rows: 6
      placeholder: 'One achievement per line'
    third_party_settings: {}
hidden: {}
```

- [ ] **Step 3: Write view display**

```yaml
# config/sync/core.entity_view_display.paragraph.work_experience.default.yml
uuid: e7f8a9b0-c1d2-3456-efab-567890123456
langcode: en
status: true
dependencies:
  config:
    - paragraphs.paragraphs_type.work_experience
  module:
    - paragraphs
id: paragraph.work_experience.default
targetEntityType: paragraph
bundle: work_experience
mode: default
content: {}
hidden:
  field_we_job_title: true
  field_we_company: true
  field_we_location: true
  field_we_start_date: true
  field_we_end_date: true
  field_we_employment_type: true
  field_we_achievements: true
```

- [ ] **Step 4: Import and verify all 7 fields exist on the paragraph**

```bash
ddev drush cim -y
ddev drush php-eval "
\$fields = \Drupal::service('entity_field.manager')->getFieldDefinitions('paragraph', 'work_experience');
\$we = array_filter(array_keys(\$fields), fn(\$k) => str_starts_with(\$k, 'field_we_'));
echo implode(', ', \$we) . PHP_EOL;
"
```

Expected output contains: `field_we_job_title, field_we_company, field_we_location, field_we_start_date, field_we_end_date, field_we_employment_type, field_we_achievements`

- [ ] **Step 5: Commit**

```bash
git add config/sync/field.field.paragraph.work_experience.field_we_job_title.yml \
  config/sync/field.field.paragraph.work_experience.field_we_company.yml \
  config/sync/field.field.paragraph.work_experience.field_we_location.yml \
  config/sync/field.field.paragraph.work_experience.field_we_start_date.yml \
  config/sync/field.field.paragraph.work_experience.field_we_end_date.yml \
  config/sync/field.field.paragraph.work_experience.field_we_employment_type.yml \
  config/sync/field.field.paragraph.work_experience.field_we_achievements.yml \
  config/sync/core.entity_form_display.paragraph.work_experience.default.yml \
  config/sync/core.entity_view_display.paragraph.work_experience.default.yml
git commit -m "feat: add work_experience paragraph field instances and displays"
```

---

## Task 3: Add `field_work_experience` to the profile node

**Files:**
- Create: `config/sync/field.storage.node.field_work_experience.yml`
- Create: `config/sync/field.field.node.profile.field_work_experience.yml`

- [ ] **Step 1: Write field storage**

```yaml
# config/sync/field.storage.node.field_work_experience.yml
uuid: f8a9b0c1-d2e3-4567-fabc-678901234567
langcode: en
status: true
dependencies:
  module:
    - entity_reference_revisions
    - node
    - paragraphs
id: node.field_work_experience
field_name: field_work_experience
entity_type: node
type: entity_reference_revisions
settings:
  target_type: paragraph
module: entity_reference_revisions
locked: false
cardinality: -1
translatable: true
indexes: {}
persist_with_no_fields: false
custom_storage: false
```

- [ ] **Step 2: Write field instance**

```yaml
# config/sync/field.field.node.profile.field_work_experience.yml
uuid: a9b0c1d2-e3f4-5678-abcd-789012345678
langcode: en
status: true
dependencies:
  config:
    - field.storage.node.field_work_experience
    - node.type.profile
    - paragraphs.paragraphs_type.work_experience
  module:
    - entity_reference_revisions
id: node.profile.field_work_experience
field_name: field_work_experience
entity_type: node
bundle: profile
label: 'Work Experience'
description: 'Add jobs most-recent-first. Empty End Date renders as "Present".'
required: false
translatable: true
default_value: {}
default_value_callback: ''
settings:
  handler: 'default:paragraph'
  handler_settings:
    target_bundles:
      work_experience: work_experience
    negate: 0
field_type: entity_reference_revisions
```

- [ ] **Step 3: Import and verify**

```bash
ddev drush cim -y
ddev drush php-eval "
\$fields = \Drupal::service('entity_field.manager')->getFieldDefinitions('node', 'profile');
echo isset(\$fields['field_work_experience']) ? 'OK' : 'MISSING';
"
```

Expected: `OK`

- [ ] **Step 4: Commit**

```bash
git add config/sync/field.storage.node.field_work_experience.yml \
  config/sync/field.field.node.profile.field_work_experience.yml
git commit -m "feat: add field_work_experience to profile node"
```

---

## Task 4: Add education fields to profile

**Files:**
- Create: `config/sync/field.storage.node.field_edu_degree.yml`
- Create: `config/sync/field.storage.node.field_edu_institution.yml`
- Create: `config/sync/field.storage.node.field_edu_location.yml`
- Create: `config/sync/field.storage.node.field_edu_start_year.yml`
- Create: `config/sync/field.storage.node.field_edu_end_year.yml`
- Create: `config/sync/field.field.node.profile.field_edu_degree.yml`
- Create: `config/sync/field.field.node.profile.field_edu_institution.yml`
- Create: `config/sync/field.field.node.profile.field_edu_location.yml`
- Create: `config/sync/field.field.node.profile.field_edu_start_year.yml`
- Create: `config/sync/field.field.node.profile.field_edu_end_year.yml`

- [ ] **Step 1: Write field storage files**

```yaml
# config/sync/field.storage.node.field_edu_degree.yml
uuid: b0c1d2e3-f4a5-6789-bcde-890123456789
langcode: en
status: true
dependencies:
  module:
    - node
id: node.field_edu_degree
field_name: field_edu_degree
entity_type: node
type: string
settings:
  max_length: 255
  case_sensitive: false
  is_ascii: false
module: core
locked: false
cardinality: 1
translatable: true
indexes: {}
persist_with_no_fields: false
custom_storage: false
```

```yaml
# config/sync/field.storage.node.field_edu_institution.yml
uuid: c1d2e3f4-a5b6-7890-cdef-901234567890
langcode: en
status: true
dependencies:
  module:
    - node
id: node.field_edu_institution
field_name: field_edu_institution
entity_type: node
type: string
settings:
  max_length: 255
  case_sensitive: false
  is_ascii: false
module: core
locked: false
cardinality: 1
translatable: true
indexes: {}
persist_with_no_fields: false
custom_storage: false
```

```yaml
# config/sync/field.storage.node.field_edu_location.yml
uuid: d2e3f4a5-b6c7-8901-defa-012345678901
langcode: en
status: true
dependencies:
  module:
    - node
id: node.field_edu_location
field_name: field_edu_location
entity_type: node
type: string
settings:
  max_length: 255
  case_sensitive: false
  is_ascii: false
module: core
locked: false
cardinality: 1
translatable: true
indexes: {}
persist_with_no_fields: false
custom_storage: false
```

```yaml
# config/sync/field.storage.node.field_edu_start_year.yml
uuid: e3f4a5b6-c7d8-9012-efab-123456789012
langcode: en
status: true
dependencies:
  module:
    - node
id: node.field_edu_start_year
field_name: field_edu_start_year
entity_type: node
type: integer
settings:
  unsigned: false
  size: normal
module: core
locked: false
cardinality: 1
translatable: true
indexes: {}
persist_with_no_fields: false
custom_storage: false
```

```yaml
# config/sync/field.storage.node.field_edu_end_year.yml
uuid: f4a5b6c7-d8e9-0123-fabc-234567890123
langcode: en
status: true
dependencies:
  module:
    - node
id: node.field_edu_end_year
field_name: field_edu_end_year
entity_type: node
type: integer
settings:
  unsigned: false
  size: normal
module: core
locked: false
cardinality: 1
translatable: true
indexes: {}
persist_with_no_fields: false
custom_storage: false
```

- [ ] **Step 2: Write field instance files**

```yaml
# config/sync/field.field.node.profile.field_edu_degree.yml
uuid: a5b6c7d8-e9f0-1234-abcd-345678901234
langcode: en
status: true
dependencies:
  config:
    - field.storage.node.field_edu_degree
    - node.type.profile
id: node.profile.field_edu_degree
field_name: field_edu_degree
entity_type: node
bundle: profile
label: 'Degree & Major'
description: 'e.g. Bachelor of Science — Computer Science'
required: false
translatable: true
default_value: {}
default_value_callback: ''
settings: {}
field_type: string
```

```yaml
# config/sync/field.field.node.profile.field_edu_institution.yml
uuid: b6c7d8e9-f0a1-2345-bcde-456789012345
langcode: en
status: true
dependencies:
  config:
    - field.storage.node.field_edu_institution
    - node.type.profile
id: node.profile.field_edu_institution
field_name: field_edu_institution
entity_type: node
bundle: profile
label: Institution
description: ''
required: false
translatable: true
default_value: {}
default_value_callback: ''
settings: {}
field_type: string
```

```yaml
# config/sync/field.field.node.profile.field_edu_location.yml
uuid: c7d8e9f0-a1b2-3456-cdef-567890123456
langcode: en
status: true
dependencies:
  config:
    - field.storage.node.field_edu_location
    - node.type.profile
id: node.profile.field_edu_location
field_name: field_edu_location
entity_type: node
bundle: profile
label: 'Education Location'
description: 'City, Country'
required: false
translatable: true
default_value: {}
default_value_callback: ''
settings: {}
field_type: string
```

```yaml
# config/sync/field.field.node.profile.field_edu_start_year.yml
uuid: d8e9f0a1-b2c3-4567-defa-678901234567
langcode: en
status: true
dependencies:
  config:
    - field.storage.node.field_edu_start_year
    - node.type.profile
id: node.profile.field_edu_start_year
field_name: field_edu_start_year
entity_type: node
bundle: profile
label: 'Education Start Year'
description: ''
required: false
translatable: true
default_value: {}
default_value_callback: ''
settings:
  min: null
  max: null
  prefix: ''
  suffix: ''
field_type: integer
```

```yaml
# config/sync/field.field.node.profile.field_edu_end_year.yml
uuid: e9f0a1b2-c3d4-5678-efab-789012345678
langcode: en
status: true
dependencies:
  config:
    - field.storage.node.field_edu_end_year
    - node.type.profile
id: node.profile.field_edu_end_year
field_name: field_edu_end_year
entity_type: node
bundle: profile
label: 'Education End Year'
description: ''
required: false
translatable: true
default_value: {}
default_value_callback: ''
settings:
  min: null
  max: null
  prefix: ''
  suffix: ''
field_type: integer
```

- [ ] **Step 3: Import and verify**

```bash
ddev drush cim -y
ddev drush php-eval "
\$fields = \Drupal::service('entity_field.manager')->getFieldDefinitions('node', 'profile');
\$edu = array_filter(array_keys(\$fields), fn(\$k) => str_starts_with(\$k, 'field_edu_'));
echo implode(', ', \$edu) . PHP_EOL;
"
```

Expected output contains: `field_edu_degree, field_edu_institution, field_edu_location, field_edu_start_year, field_edu_end_year`

- [ ] **Step 4: Commit**

```bash
git add config/sync/field.storage.node.field_edu_degree.yml \
  config/sync/field.storage.node.field_edu_institution.yml \
  config/sync/field.storage.node.field_edu_location.yml \
  config/sync/field.storage.node.field_edu_start_year.yml \
  config/sync/field.storage.node.field_edu_end_year.yml \
  config/sync/field.field.node.profile.field_edu_degree.yml \
  config/sync/field.field.node.profile.field_edu_institution.yml \
  config/sync/field.field.node.profile.field_edu_location.yml \
  config/sync/field.field.node.profile.field_edu_start_year.yml \
  config/sync/field.field.node.profile.field_edu_end_year.yml
git commit -m "feat: add education fields to profile node"
```

---

## Task 5: Add `field_languages` to profile

**Files:**
- Create: `config/sync/field.storage.node.field_languages.yml`
- Create: `config/sync/field.field.node.profile.field_languages.yml`

- [ ] **Step 1: Write field storage (multi-value string)**

```yaml
# config/sync/field.storage.node.field_languages.yml
uuid: f0a1b2c3-d4e5-6789-fabc-890123456789
langcode: en
status: true
dependencies:
  module:
    - node
id: node.field_languages
field_name: field_languages
entity_type: node
type: string
settings:
  max_length: 255
  case_sensitive: false
  is_ascii: false
module: core
locked: false
cardinality: -1
translatable: true
indexes: {}
persist_with_no_fields: false
custom_storage: false
```

- [ ] **Step 2: Write field instance**

```yaml
# config/sync/field.field.node.profile.field_languages.yml
uuid: a1b2c3d4-e5f6-7890-abcd-901234567890
langcode: en
status: true
dependencies:
  config:
    - field.storage.node.field_languages
    - node.type.profile
id: node.profile.field_languages
field_name: field_languages
entity_type: node
bundle: profile
label: Languages
description: 'One entry per language, e.g. "Spanish — Native" or "English — Fluent (C1)"'
required: false
translatable: true
default_value: {}
default_value_callback: ''
settings: {}
field_type: string
```

- [ ] **Step 3: Import and verify**

```bash
ddev drush cim -y
ddev drush php-eval "
\$fields = \Drupal::service('entity_field.manager')->getFieldDefinitions('node', 'profile');
echo isset(\$fields['field_languages']) ? 'OK' : 'MISSING';
"
```

Expected: `OK`

- [ ] **Step 4: Commit**

```bash
git add config/sync/field.storage.node.field_languages.yml \
  config/sync/field.field.node.profile.field_languages.yml
git commit -m "feat: add field_languages to profile node"
```

---

## Task 6: Expose new fields in profile form display and PDF view display

**Files:**
- Modify: `config/sync/core.entity_form_display.node.profile.default.yml`
- Modify: `config/sync/core.entity_view_display.node.profile.pdf.yml`

- [ ] **Step 1: Update form display — add new fields to `content` and update `dependencies`**

Replace the entire file with the following (adds all new fields to the form):

```yaml
# config/sync/core.entity_form_display.node.profile.default.yml
uuid: 2d02b3dd-8057-4eb8-95df-54f360397de9
langcode: en
status: true
dependencies:
  config:
    - field.field.node.profile.body
    - field.field.node.profile.field_bio
    - field.field.node.profile.field_cv_file
    - field.field.node.profile.field_drupalorg_url
    - field.field.node.profile.field_edu_degree
    - field.field.node.profile.field_edu_end_year
    - field.field.node.profile.field_edu_institution
    - field.field.node.profile.field_edu_location
    - field.field.node.profile.field_edu_start_year
    - field.field.node.profile.field_email
    - field.field.node.profile.field_github_url
    - field.field.node.profile.field_languages
    - field.field.node.profile.field_linkedin_url
    - field.field.node.profile.field_location
    - field.field.node.profile.field_photo
    - field.field.node.profile.field_subtitle
    - field.field.node.profile.field_work_experience
    - field.field.node.profile.field_years_experience
    - node.type.profile
  module:
    - datetime
    - entity_reference_revisions
    - paragraphs
    - path
    - text
id: node.profile.default
targetEntityType: node
bundle: profile
mode: default
content:
  body:
    type: text_textarea_with_summary
    weight: 121
    region: content
    settings:
      rows: 9
      summary_rows: 3
      placeholder: ''
      show_summary: false
    third_party_settings: {}
  created:
    type: datetime_timestamp
    weight: 10
    region: content
    settings: {}
    third_party_settings: {}
  field_bio:
    type: text_textarea
    weight: 5
    region: content
    settings:
      rows: 12
      placeholder: ''
    third_party_settings: {}
  field_cv_file:
    type: file_generic
    weight: 20
    region: content
    settings:
      progress_indicator: throbber
    third_party_settings: {}
  field_drupalorg_url:
    type: link_default
    weight: 14
    region: content
    settings:
      placeholder_url: ''
      placeholder_title: ''
    third_party_settings: {}
  field_edu_degree:
    type: string_textfield
    weight: 30
    region: content
    settings:
      size: 60
      placeholder: 'e.g. Bachelor of Science — Computer Science'
    third_party_settings: {}
  field_edu_end_year:
    type: number
    weight: 34
    region: content
    settings:
      placeholder: ''
    third_party_settings: {}
  field_edu_institution:
    type: string_textfield
    weight: 31
    region: content
    settings:
      size: 60
      placeholder: 'University or institution name'
    third_party_settings: {}
  field_edu_location:
    type: string_textfield
    weight: 32
    region: content
    settings:
      size: 60
      placeholder: 'City, Country'
    third_party_settings: {}
  field_edu_start_year:
    type: number
    weight: 33
    region: content
    settings:
      placeholder: ''
    third_party_settings: {}
  field_email:
    type: email_default
    weight: 10
    region: content
    settings:
      placeholder: ''
    third_party_settings: {}
  field_github_url:
    type: link_default
    weight: 12
    region: content
    settings:
      placeholder_url: ''
      placeholder_title: ''
    third_party_settings: {}
  field_languages:
    type: string_textfield
    weight: 40
    region: content
    settings:
      size: 60
      placeholder: 'e.g. Spanish — Native'
    third_party_settings: {}
  field_linkedin_url:
    type: link_default
    weight: 13
    region: content
    settings:
      placeholder_url: ''
      placeholder_title: ''
    third_party_settings: {}
  field_location:
    type: string_textfield
    weight: 11
    region: content
    settings:
      size: 60
      placeholder: ''
    third_party_settings: {}
  field_photo:
    type: image_image
    weight: 4
    region: content
    settings:
      progress_indicator: throbber
      preview_image_style: thumbnail
    third_party_settings: {}
  field_subtitle:
    type: string_textfield
    weight: 3
    region: content
    settings:
      size: 60
      placeholder: ''
    third_party_settings: {}
  field_work_experience:
    type: paragraphs
    weight: 25
    region: content
    settings:
      title: 'Work Experience'
      title_plural: 'Work Experience entries'
      edit_mode: open
      closed_mode: summary
      autocollapse: none
      closed_mode_threshold: 0
      add_mode: dropdown
      form_display_mode: default
      default_paragraph_type: work_experience
      features:
        duplicate: '0'
        collapse_edit_all: collapse_edit_all
    third_party_settings: {}
  field_years_experience:
    type: number
    weight: 2
    region: content
    settings:
      placeholder: ''
    third_party_settings: {}
  path:
    type: path
    weight: 30
    region: content
    settings: {}
    third_party_settings: {}
  simple_sitemap:
    weight: 10
    region: content
    settings: {}
    third_party_settings: {}
  status:
    type: boolean_checkbox
    weight: 120
    region: content
    settings:
      display_label: true
    third_party_settings: {}
  title:
    type: string_textfield
    weight: -5
    region: content
    settings:
      size: 60
      placeholder: ''
    third_party_settings: {}
  uid:
    type: entity_reference_autocomplete
    weight: 5
    region: content
    settings:
      match_operator: CONTAINS
      match_limit: 10
      size: 60
      placeholder: ''
    third_party_settings: {}
  url_redirects:
    weight: 50
    region: content
    settings: {}
    third_party_settings: {}
hidden:
  promote: true
  sticky: true
```

- [ ] **Step 2: Update PDF view display — add new fields to `hidden`**

Replace the entire file:

```yaml
# config/sync/core.entity_view_display.node.profile.pdf.yml
uuid: bcb0b1f4-3499-450c-99d3-9ef227d09213
langcode: en
status: true
dependencies:
  config:
    - core.entity_view_mode.node.pdf
    - field.field.node.profile.field_bio
    - field.field.node.profile.field_cv_file
    - field.field.node.profile.field_drupalorg_url
    - field.field.node.profile.field_edu_degree
    - field.field.node.profile.field_edu_end_year
    - field.field.node.profile.field_edu_institution
    - field.field.node.profile.field_edu_location
    - field.field.node.profile.field_edu_start_year
    - field.field.node.profile.field_email
    - field.field.node.profile.field_github_url
    - field.field.node.profile.field_languages
    - field.field.node.profile.field_linkedin_url
    - field.field.node.profile.field_location
    - field.field.node.profile.field_photo
    - field.field.node.profile.field_subtitle
    - field.field.node.profile.field_work_experience
    - field.field.node.profile.field_years_experience
    - node.type.profile
  module:
    - node
id: node.profile.pdf
targetEntityType: node
bundle: profile
mode: pdf
content: {}
hidden:
  body: true
  entity_print_view_epub: true
  entity_print_view_pdf: true
  entity_print_view_word_docx: true
  field_bio: true
  field_cv_file: true
  field_drupalorg_url: true
  field_edu_degree: true
  field_edu_end_year: true
  field_edu_institution: true
  field_edu_location: true
  field_edu_start_year: true
  field_email: true
  field_github_url: true
  field_languages: true
  field_linkedin_url: true
  field_location: true
  field_photo: true
  field_subtitle: true
  field_work_experience: true
  field_years_experience: true
  links: true
  search_api_excerpt: true
```

- [ ] **Step 3: Import and verify admin form loads without errors**

```bash
ddev drush cim -y
ddev drush cr
```

Then open https://portfolio.ddev.site/admin/content in the browser and navigate to the profile node edit form. All new fields should be visible.

- [ ] **Step 4: Commit**

```bash
git add config/sync/core.entity_form_display.node.profile.default.yml \
  config/sync/core.entity_view_display.node.profile.pdf.yml
git commit -m "feat: expose new CV fields in profile form and PDF view display"
```

---

## Task 7: Update `_portfolio_core_preprocess_profile_pdf`

**Files:**
- Modify: `web/modules/custom/portfolio_core/portfolio_core.module`

- [ ] **Step 1: Replace `_portfolio_core_preprocess_profile_pdf` with the full implementation**

Find the function starting at line ~199 and replace it entirely:

```php
/**
 * Prepares variables for profile PDF template.
 */
function _portfolio_core_preprocess_profile_pdf(array &$variables, NodeInterface $node): void {
  // Profile identity.
  $variables['profile_name'] = $node->label();
  $variables['profile_subtitle'] = $node->get('field_subtitle')->value ?? '';
  $variables['profile_location'] = $node->get('field_location')->value ?? '';
  $variables['profile_email'] = $node->get('field_email')->value ?? '';
  $variables['profile_years_experience'] = $node->get('field_years_experience')->value ?? '';

  // Bio as plain text for the Professional Summary section.
  $bio_field = $node->get('field_bio');
  $variables['profile_bio'] = !$bio_field->isEmpty()
    ? strip_tags((string) check_markup($bio_field->value, $bio_field->format))
    : '';

  // Contact links.
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

  // Core Skills — grouped by category, sorted alphabetically within each group.
  $skill_nodes = \Drupal::entityTypeManager()
    ->getStorage('node')
    ->loadByProperties(['type' => 'skill', 'status' => 1]);

  $skills_by_category = [];
  foreach ($skill_nodes as $skill) {
    $category_terms = $skill->get('field_category')->referencedEntities();
    $category = !empty($category_terms) ? $category_terms[0]->label() : 'Other';
    $skills_by_category[$category][] = $skill->label();
  }
  ksort($skills_by_category);
  foreach ($skills_by_category as $category => &$labels) {
    sort($labels);
  }
  unset($labels);
  $variables['profile_skills'] = $skills_by_category;

  // Work Experience — iterate paragraphs, format dates, split achievements.
  $date_formatter = \Drupal::service('date.formatter');
  $work_items = [];
  foreach ($node->get('field_work_experience')->referencedEntities() as $paragraph) {
    $start_raw = $paragraph->get('field_we_start_date')->value;
    $end_raw = $paragraph->get('field_we_end_date')->value;

    $start_display = $start_raw
      ? $date_formatter->format(strtotime($start_raw), 'custom', 'M Y')
      : '';
    $end_display = $end_raw
      ? $date_formatter->format(strtotime($end_raw), 'custom', 'M Y')
      : 'Present';

    $achievements_raw = $paragraph->get('field_we_achievements')->value ?? '';
    $achievements = array_values(
      array_filter(
        array_map('trim', preg_split('/\r\n|\r|\n/', $achievements_raw)),
        fn(string $line): bool => $line !== '',
      ),
    );

    $work_items[] = [
      'job_title' => $paragraph->get('field_we_job_title')->value ?? '',
      'company' => $paragraph->get('field_we_company')->value ?? '',
      'location' => $paragraph->get('field_we_location')->value ?? '',
      'employment_type' => $paragraph->get('field_we_employment_type')->value ?? '',
      'start' => $start_display,
      'end' => $end_display,
      'achievements' => $achievements,
    ];
  }
  $variables['profile_work_experience'] = $work_items;

  // Education.
  $variables['profile_edu_degree'] = $node->get('field_edu_degree')->value ?? '';
  $variables['profile_edu_institution'] = $node->get('field_edu_institution')->value ?? '';
  $variables['profile_edu_location'] = $node->get('field_edu_location')->value ?? '';
  $variables['profile_edu_start_year'] = $node->get('field_edu_start_year')->value ?? '';
  $variables['profile_edu_end_year'] = $node->get('field_edu_end_year')->value ?? '';

  // Languages — collect all multi-value string items.
  $variables['profile_languages'] = array_map(
    fn($item) => $item->value,
    iterator_to_array($node->get('field_languages')),
  );

  // Entity Print bypasses the standard render cache; explicit cache tags
  // ensure invalidation when the profile node is updated.
  $variables['#cache']['tags'] = array_merge(
    $variables['#cache']['tags'] ?? [],
    $node->getCacheTags(),
  );
}
```

- [ ] **Step 2: Clear cache and verify preprocess runs without errors**

```bash
ddev drush cr
ddev drush php-eval "
\$node = \Drupal::entityTypeManager()->getStorage('node')->loadByProperties(['type' => 'profile']);
\$node = reset(\$node);
if (\$node) {
  echo 'Profile node id: ' . \$node->id() . PHP_EOL;
  echo 'WE paragraphs: ' . \$node->get('field_work_experience')->count() . PHP_EOL;
  echo 'Languages count: ' . \$node->get('field_languages')->count() . PHP_EOL;
} else {
  echo 'No profile node found.';
}
"
```

Expected: prints profile node id. WE paragraphs and languages will be 0 until data is entered — that is fine at this stage.

- [ ] **Step 3: Commit**

```bash
git add web/modules/custom/portfolio_core/portfolio_core.module
git commit -m "feat: update profile PDF preprocess with skills, work experience, education, and languages"
```

---

## Task 8: Rewrite the PDF Twig template

**Files:**
- Modify: `web/themes/custom/portfolio_theme/templates/content/node--profile--pdf.html.twig`

- [ ] **Step 1: Replace the template entirely**

```twig
{# node--profile--pdf.html.twig #}
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>{{ profile_name }} — CV</title>
</head>
<body>

<div class="cv">

  {# ── HEADER ─────────────────────────────────────────────── #}
  <div class="cv__header">
    <h1 class="cv__name">{{ profile_name }}</h1>
    {% if profile_subtitle %}
      <p class="cv__subtitle">{{ profile_subtitle }}</p>
    {% endif %}
  </div>

  {# ── CONTACT INFO ────────────────────────────────────────── #}
  <div class="cv__contact-row">
    {%- set contact_items = [] -%}
    {%- if profile_location -%}{%- set contact_items = contact_items|merge([profile_location]) -%}{%- endif -%}
    {%- if profile_email -%}{%- set contact_items = contact_items|merge([profile_email]) -%}{%- endif -%}
    {%- if profile_github_url -%}{%- set contact_items = contact_items|merge([profile_github_url]) -%}{%- endif -%}
    {%- if profile_linkedin_url -%}{%- set contact_items = contact_items|merge([profile_linkedin_url]) -%}{%- endif -%}
    {%- if profile_drupalorg_url -%}{%- set contact_items = contact_items|merge([profile_drupalorg_url]) -%}{%- endif -%}
    {{ contact_items|join(' · ') }}
  </div>

  {# ── PROFESSIONAL SUMMARY ────────────────────────────────── #}
  {% if profile_bio %}
    <div class="cv__section">
      <h2 class="cv__section-title">Professional Summary</h2>
      <p class="cv__bio">{{ profile_bio }}</p>
    </div>
  {% endif %}

  {# ── CORE SKILLS ─────────────────────────────────────────── #}
  {% if profile_skills %}
    <div class="cv__section">
      <h2 class="cv__section-title">Core Skills</h2>
      <div class="cv__skills">
        {% for category, labels in profile_skills %}
          <div class="cv__skills-group">
            <span class="cv__skills-category">{{ category }}:</span>
            <span class="cv__skills-list">{{ labels|join(', ') }}</span>
          </div>
        {% endfor %}
      </div>
    </div>
  {% endif %}

  {# ── WORK EXPERIENCE ─────────────────────────────────────── #}
  {% if profile_work_experience %}
    <div class="cv__section">
      <h2 class="cv__section-title">Work Experience</h2>
      {% for job in profile_work_experience %}
        <div class="cv__work-item">
          <div class="cv__work-header">
            <strong>{{ job.job_title }}</strong>
            {% if job.company %} — {{ job.company }}{% endif %}
            {% if job.employment_type %}
              <span class="cv__work-type">({{ job.employment_type }})</span>
            {% endif %}
          </div>
          <div class="cv__work-meta">
            {% if job.location %}{{ job.location }}{% endif %}
            {% if job.location and job.start %} | {% endif %}
            {% if job.start %}{{ job.start }} – {{ job.end }}{% endif %}
          </div>
          {% if job.achievements %}
            <ul class="cv__achievements">
              {% for bullet in job.achievements %}
                <li>{{ bullet }}</li>
              {% endfor %}
            </ul>
          {% endif %}
        </div>
      {% endfor %}
    </div>
  {% endif %}

  {# ── EDUCATION ───────────────────────────────────────────── #}
  {% if profile_edu_degree or profile_edu_institution %}
    <div class="cv__section">
      <h2 class="cv__section-title">Education</h2>
      <div class="cv__edu-item">
        <div class="cv__edu-header">
          {% if profile_edu_degree %}<strong>{{ profile_edu_degree }}</strong>{% endif %}
          {% if profile_edu_institution %} — {{ profile_edu_institution }}{% endif %}
        </div>
        <div class="cv__edu-meta">
          {% if profile_edu_location %}{{ profile_edu_location }}{% endif %}
          {% if profile_edu_location and profile_edu_start_year %} | {% endif %}
          {% if profile_edu_start_year %}{{ profile_edu_start_year }}{% endif %}
          {% if profile_edu_start_year and profile_edu_end_year %} – {{ profile_edu_end_year }}{% endif %}
        </div>
      </div>
    </div>
  {% endif %}

  {# ── LANGUAGES ───────────────────────────────────────────── #}
  {% if profile_languages %}
    <div class="cv__section">
      <h2 class="cv__section-title">Languages</h2>
      <ul class="cv__languages">
        {% for lang in profile_languages %}
          <li>{{ lang }}</li>
        {% endfor %}
      </ul>
    </div>
  {% endif %}

</div>

</body>
</html>
```

- [ ] **Step 2: Clear cache**

```bash
ddev drush cr
```

- [ ] **Step 3: Commit**

```bash
git add web/themes/custom/portfolio_theme/templates/content/node--profile--pdf.html.twig
git commit -m "feat: rewrite PDF CV template with full section structure"
```

---

## Task 9: Refactor CSS into SCSS and update styles

**Files:**
- Create: `web/themes/custom/portfolio_theme/scss/layout/_cv-pdf.scss`
- Modify: `web/themes/custom/portfolio_theme/css/layout/cv-pdf.css`

Note: `cv-pdf.scss` (no underscore) is a standalone SCSS compile target — NOT a partial and NOT imported via `main.scss`. The build script compiles it directly to `css/layout/cv-pdf.css`. Run `ddev theme-build` after any edits.

- [ ] **Step 1: Create `cv-pdf.scss`**

```scss
// scss/layout/cv-pdf.scss
// Compiled independently → css/layout/cv-pdf.css
// Attached by EntityPrintCssSubscriber only when printing a profile node.

@page {
  size: A4;
  margin: 18mm;
}

body {
  font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
  font-size: 11pt;
  line-height: 1.25;
  color: #1a1a1a;
  margin: 0;
  padding: 0;
}

.cv {
  width: 100%;
}

// ── Header ──────────────────────────────────────────────────

.cv__header {
  margin-bottom: 6pt;
}

.cv__name {
  font-size: 22pt;
  font-weight: 700;
  margin: 0 0 4pt;
  color: #1a1a1a;
}

.cv__subtitle {
  font-size: 13pt;
  color: #1d6a72;
  margin: 0;
}

// ── Contact row ──────────────────────────────────────────────

.cv__contact-row {
  font-size: 10pt;
  color: #444;
  margin-bottom: 14pt;
  border-bottom: 0.5pt solid #ccc;
  padding-bottom: 8pt;
}

// ── Sections ─────────────────────────────────────────────────

.cv__section {
  margin-bottom: 14pt;
}

.cv__section-title {
  font-size: 11pt;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: #1d6a72;
  border-bottom: 0.5pt solid #ccc;
  padding-bottom: 3pt;
  margin: 0 0 7pt;
}

// ── Professional Summary ──────────────────────────────────────

.cv__bio {
  font-size: 10.5pt;
  line-height: 1.4;
  color: #1a1a1a;
  margin: 0;
}

// ── Core Skills ───────────────────────────────────────────────

.cv__skills-group {
  font-size: 10pt;
  margin-bottom: 3pt;
}

.cv__skills-category {
  font-weight: 700;
  margin-right: 4pt;
}

.cv__skills-list {
  color: #333;
}

// ── Work Experience ───────────────────────────────────────────

.cv__work-item {
  margin-bottom: 10pt;
}

.cv__work-header {
  font-size: 10.5pt;
  font-weight: 400;
  margin-bottom: 1pt;
}

.cv__work-type {
  font-size: 9.5pt;
  color: #666;
  font-style: italic;
}

.cv__work-meta {
  font-size: 9.5pt;
  color: #555;
  margin-bottom: 4pt;
}

.cv__achievements {
  margin: 0;
  padding-left: 14pt;
  font-size: 10pt;
  color: #1a1a1a;

  li {
    margin-bottom: 2pt;
    line-height: 1.3;
  }
}

// ── Education ─────────────────────────────────────────────────

.cv__edu-item {
  font-size: 10pt;
}

.cv__edu-header {
  margin-bottom: 1pt;
}

.cv__edu-meta {
  font-size: 9.5pt;
  color: #555;
}

// ── Languages ─────────────────────────────────────────────────

.cv__languages {
  margin: 0;
  padding: 0;
  list-style: none;
  font-size: 10pt;

  li {
    margin-bottom: 2pt;
  }
}
```

- [ ] **Step 2: Add `cv-pdf.scss` as a compile entry in `package.json`**

The build script uses explicit `source:target` pairs. Add `scss/layout/cv-pdf.scss:css/layout/cv-pdf.css` to all three scripts in `web/themes/custom/portfolio_theme/package.json`:

```json
{
  "name": "portfolio-theme",
  "private": true,
  "scripts": {
    "build": "sass --no-source-map --style=compressed scss/main.scss:css/main.css scss/layout/cv-pdf.scss:css/layout/cv-pdf.css components/article-teaser/article-teaser.scss:components/article-teaser/article-teaser.css components/project-card/project-card.scss:components/project-card/project-card.css components/skill-card/skill-card.scss:components/skill-card/skill-card.css components/feature-badge/feature-badge.scss:components/feature-badge/feature-badge.css",
    "build:dev": "sass --style=expanded scss/main.scss:css/main.css scss/layout/cv-pdf.scss:css/layout/cv-pdf.css components/article-teaser/article-teaser.scss:components/article-teaser/article-teaser.css components/project-card/project-card.scss:components/project-card/project-card.css components/skill-card/skill-card.scss:components/skill-card/skill-card.css components/feature-badge/feature-badge.scss:components/feature-badge/feature-badge.css",
    "watch": "sass --watch scss/main.scss:css/main.css scss/layout/cv-pdf.scss:css/layout/cv-pdf.css components/article-teaser/article-teaser.scss:components/article-teaser/article-teaser.css components/project-card/project-card.scss:components/project-card/project-card.css components/skill-card/skill-card.scss:components/skill-card/skill-card.css components/feature-badge/feature-badge.scss:components/feature-badge/feature-badge.css"
  },
  "devDependencies": {
    "sass": "^1.77.0"
  }
}
```

- [ ] **Step 4: Compile and verify output**

```bash
ddev theme-build
head -5 /work/projects/portfolio/web/themes/custom/portfolio_theme/css/layout/cv-pdf.css
```

Expected: First lines of compressed CSS output containing `@page` and `size:A4`.

- [ ] **Step 5: Commit**

```bash
git add web/themes/custom/portfolio_theme/scss/layout/cv-pdf.scss \
  web/themes/custom/portfolio_theme/css/layout/cv-pdf.css \
  web/themes/custom/portfolio_theme/package.json
git commit -m "feat: refactor cv-pdf styles into SCSS with A4 page, teal accent, and all CV sections"
```

---

## Task 10: Enter data and verify the full PDF

- [ ] **Step 1: Open the profile edit form and add data**

Navigate to https://portfolio.ddev.site/admin/content, find the profile node, click Edit.

Add at least one Work Experience paragraph:
- Job Title: `Senior Drupal Developer`
- Company: `(your current/most recent employer)`
- Location: `Costa Rica, Guanacaste`
- Start Date: a valid date
- End Date: leave empty (renders as "Present")
- Achievements: paste a few lines, one per line

Fill Education fields:
- Degree: your degree
- Institution: your institution
- Location: city, country
- Start Year / End Year: years

Add Languages:
- `Spanish — Native`
- `English — Fluent (C1)`

Save the node.

- [ ] **Step 2: Generate the PDF and inspect all sections**

```bash
ddev drush cr
```

Navigate to https://portfolio.ddev.site/about, click the "Download CV" / Entity Print PDF link.

Verify the PDF contains all sections in order:
- [ ] Header: name + subtitle
- [ ] Contact row: location · email · GitHub · LinkedIn · Drupal.org
- [ ] Professional Summary: bio text
- [ ] Core Skills: grouped by category
- [ ] Work Experience: job entry with achievements as bullets
- [ ] Education: degree + institution + years
- [ ] Languages: Spanish, English

- [ ] **Step 3: Export config to capture any view/form display drift**

```bash
ddev drush cex -y
git diff config/sync/
```

If any display config changed, add those files:

```bash
git add config/sync/
git commit -m "chore: export config after CV data entry and PDF verification"
```

---

## Task 11: Final config export and commit

- [ ] **Step 1: Full config export**

```bash
ddev drush cex -y
git status
```

- [ ] **Step 2: Stage and commit any remaining config changes**

```bash
git add config/sync/
git commit -m "chore: export final config for CV PDF refactor"
```

- [ ] **Step 3: Verify site is clean**

```bash
ddev drush cr
ddev drush status
```

Expected: no errors, site version displayed cleanly.
