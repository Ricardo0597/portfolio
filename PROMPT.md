# PROMPT.md — Portfolio Site Build Specification

This file is the master build instruction set for Claude Code.
Each phase is a self-contained unit of work. Execute phases in order.
Do not skip phases. After each phase, export config and commit.

Read CLAUDE.md completely before starting Phase 0.

---

## Project Goal

Build a personal Drupal 11 portfolio site that:
- Presents professional information (bio, skills, projects, articles).
- Demonstrates technical depth through deliberate implementation patterns.
- Is clean, maintainable, and deployable to Acquia Cloud or Platform.sh.
- Uses modern Drupal 11 patterns throughout.

---

## Phase 0 — Environment Bootstrap

**Goal:** Working Drupal 11 install with DDEV, correct config sync path, and
base modules installed.

### Steps

1. Verify DDEV is running: `ddev start`

2. Install Drupal 11 via Composer if not already installed:
   ```bash
   ddev composer create drupal/recommended-project:^11 .
   ```

3. Run site install:
   ```bash
   ddev drush site:install --account-name=admin \
     --account-pass=admin \
     --account-mail=admin@portfolio.local \
     --site-name="Portfolio" \
     --locale=en -y
   ```

4. Set config sync path in `web/sites/default/settings.php`:
   ```php
   $settings['config_sync_directory'] = '../config/sync';
   ```

5. Create `config/sync/` directory:
   ```bash
   mkdir -p config/sync
   ```

6. Install admin theme (Gin):
   ```bash
   ddev composer require drupal/gin
   ddev drush en gin -y
   ddev drush theme:set-default gin
   ddev drush config-set system.theme admin gin -y
   ```

7. Install base contributed modules:
   ```bash
   ddev composer require \
     drupal/admin_toolbar \
     drupal/paragraphs \
     drupal/field_group \
     drupal/pathauto \
     drupal/token \
     drupal/metatag \
     drupal/simple_sitemap \
     drupal/redirect \
     drupal/search_api \
     drupal/search_api_db \
     drupal/facets \
     drupal/restui

   ddev drush en \
     admin_toolbar admin_toolbar_tools \
     paragraphs field_group \
     pathauto token \
     metatag metatag_open_graph \
     simple_sitemap redirect \
     search_api search_api_db facets \
     rest serialization restui \
     jsonapi -y
   ```

8. Export initial config and commit:
   ```bash
   ddev drush cex -y
   git add config/sync/ composer.json composer.lock
   git commit -m "chore: bootstrap Drupal 11 with base modules"
   ```

**Checkpoint:** Site loads at https://portfolio.ddev.site, Gin admin theme
is active, all modules enabled without errors.

---

## Phase 1 — Custom Module Scaffold

**Goal:** Create `portfolio_core` module with correct structure, ready to
receive all custom code for subsequent phases.

### Steps

1. Create the module directory and scaffold:

   `web/modules/custom/portfolio_core/portfolio_core.info.yml`
   ```yaml
   name: 'Portfolio Core'
   type: module
   description: 'Core custom functionality for the portfolio site.'
   package: Portfolio
   core_version_requirement: ^11
   ```

   `web/modules/custom/portfolio_core/portfolio_core.module`
   ```php
   <?php
   declare(strict_types=1);
   /**
    * @file
    * Primary module hooks for Portfolio Core.
    */
   ```

   `web/modules/custom/portfolio_core/portfolio_core.services.yml`
   ```yaml
   services: {}
   ```

   `web/modules/custom/portfolio_core/portfolio_core.routing.yml`
   ```yaml
   # Routes defined here in later phases.
   ```

2. Create subdirectories:
   ```bash
   mkdir -p web/modules/custom/portfolio_core/src/Controller
   mkdir -p web/modules/custom/portfolio_core/src/Plugin/Block
   mkdir -p web/modules/custom/portfolio_core/src/Plugin/rest/resource
   mkdir -p web/modules/custom/portfolio_core/src/EventSubscriber
   mkdir -p web/modules/custom/portfolio_core/src/TwigExtension
   mkdir -p web/modules/custom/portfolio_core/src/Service
   mkdir -p web/modules/custom/portfolio_core/tests/src/Unit
   ```

3. Enable the module:
   ```bash
   ddev drush en portfolio_core -y
   ```

4. Export config and commit:
   ```bash
   ddev drush cex -y
   git add web/modules/custom/portfolio_core/ config/sync/
   git commit -m "feat: scaffold portfolio_core custom module"
   ```

**Checkpoint:** Module enabled with no errors. `ddev drush status` shows
it in the module list.

---

## Phase 2 — Content Architecture

**Goal:** All content types, taxonomy vocabularies, and paragraph types
created with correct fields, labels, and form display configuration.

### Taxonomy Vocabularies

Create all four vocabularies via Drush or config:

| Machine name       | Label            |
|--------------------|------------------|
| `skill_category`   | Skill Category   |
| `technology_stack` | Technology Stack |
| `project_status`   | Project Status   |
| `article_tags`     | Article Tags     |

Seed `skill_category` with: Backend, Frontend, DevOps, CMS, Architecture, Testing
Seed `technology_stack` with: Drupal, PHP, JavaScript, CSS, MySQL, Git, Docker, Composer
Seed `project_status` with: Live, Work in Progress, Archived

### Paragraph Types

#### `code_snippet`
| Field                | Type          | Settings                        |
|----------------------|---------------|---------------------------------|
| `field_language`     | List (text)   | PHP, JS, CSS, Twig, YAML, Bash  |
| `field_code`         | Text (plain)  | Unlimited rows                  |
| `field_description`  | Text (plain)  | Optional                        |

#### `image_caption`
| Field              | Type       | Settings           |
|--------------------|------------|--------------------|
| `field_image`      | Image      | Required           |
| `field_caption`    | Text (plain)| Optional          |

#### `key_takeaway`
| Field           | Type          | Settings         |
|-----------------|---------------|------------------|
| `field_heading` | Text (plain)  | Required         |
| `field_body`    | Text (formatted)| Required       |

#### `external_reference`
| Field           | Type          | Settings         |
|-----------------|---------------|------------------|
| `field_label`   | Text (plain)  | Required         |
| `field_url`     | Link          | External only    |
| `field_note`    | Text (plain)  | Optional         |

### Content Types

#### `profile` — Profile
| Field                  | Type              | Settings                    |
|------------------------|-------------------|-----------------------------|
| `field_subtitle`       | Text (plain)      | e.g., "Senior Drupal Dev"   |
| `field_bio`            | Text (formatted, long) | Full bio               |
| `field_photo`          | Image             | Profile photo               |
| `field_location`       | Text (plain)      | City, Country               |
| `field_email`          | Email             | Contact email               |
| `field_github_url`     | Link              | GitHub profile              |
| `field_linkedin_url`   | Link              | LinkedIn profile            |
| `field_cv_file`        | File              | PDF only                    |
| `field_years_experience`| Integer          | Numeric                     |

Node title = Full name. Promote to front page: off. Revision: off.

#### `skill` — Skill
| Field                  | Type                    | Settings              |
|------------------------|-------------------------|-----------------------|
| `field_skill_level`    | List (integer)          | 1=Beginner … 5=Expert |
| `field_category`       | Entity ref → `skill_category` | Required        |
| `field_description`    | Text (formatted)        | Optional              |
| `field_icon_class`     | Text (plain)            | CSS class or slug     |
| `field_featured`       | Boolean                 | Default: off          |

#### `project` — Project
| Field                  | Type                          | Settings                 |
|------------------------|-------------------------------|--------------------------|
| `field_summary`        | Text (plain, long)            | Short description        |
| `field_thumbnail`      | Image                         | Project thumbnail        |
| `field_stack`          | Entity ref → `technology_stack` | Multiple               |
| `field_status`         | Entity ref → `project_status` | Required                 |
| `field_repo_url`       | Link                          | Optional, external       |
| `field_demo_url`       | Link                          | Optional, external       |
| `field_start_date`     | Date                          | Required                 |
| `field_end_date`       | Date                          | Optional                 |
| `field_featured`       | Boolean                       | Default: off             |
| `field_content`        | Entity ref revisions → Paragraph | Multiple, unlimited   |
|                        |                               | Allowed: code_snippet, image_caption, key_takeaway, external_reference |

#### `article` — Article
| Field              | Type                      | Settings             |
|--------------------|---------------------------|----------------------|
| `field_summary`    | Text (plain, long)        | Teaser text          |
| `field_hero_image` | Image                     | Optional             |
| `field_tags`       | Entity ref → `article_tags`| Multiple            |
| `field_stack`      | Entity ref → `technology_stack` | Multiple        |
| `field_content`    | Entity ref revisions → Paragraph | Multiple      |
|                    |                           | Allowed: code_snippet, image_caption, key_takeaway, external_reference |

#### `feature_demo` — Feature Demo
| Field                  | Type                          | Settings               |
|------------------------|-------------------------------|------------------------|
| `field_summary`        | Text (plain, long)            | What this feature shows|
| `field_drupal_pattern` | List (text)                   | See values below       |
| `field_difficulty`     | List (text)                   | Beginner/Intermediate/Advanced |
| `field_stack`          | Entity ref → `technology_stack`| Multiple              |
| `field_content`        | Entity ref revisions → Paragraph| Multiple             |
|                        |                               | Allowed: all paragraph types |
| `field_source_path`    | Text (plain)                  | Path to file in repo  |

`field_drupal_pattern` values:
```
Content Type | Paragraph | Block Plugin | REST Resource | Event Subscriber |
Twig Extension | SDC Component | Views | Search API | Layout Builder |
Config Entity | Custom Service | Form API | Hook
```

### Pathauto Patterns

After installing content types, configure pathauto patterns:

| Content Type  | Pattern                         |
|---------------|---------------------------------|
| profile       | `/about`                        |
| skill         | `/skills/[node:title]`          |
| project       | `/projects/[node:title]`        |
| article       | `/articles/[node:title]`        |
| feature_demo  | `/features/[node:title]`        |

### Commit

```bash
ddev drush cex -y
git add config/sync/
git commit -m "feat: define content architecture - types, paragraphs, taxonomies"
```

**Checkpoint:** All content types visible in admin, all fields present,
pathauto patterns saved. Create one test node per content type to verify.

---

## Phase 3 — Custom Theme

**Goal:** `portfolio_theme` installed as default frontend theme with correct
SDC structure, base CSS variables, and typography.

### Theme Info

`web/themes/custom/portfolio_theme/portfolio_theme.info.yml`
```yaml
name: 'Portfolio Theme'
type: theme
description: 'Custom theme for the portfolio site.'
core_version_requirement: ^11
base theme: false
libraries:
  - portfolio_theme/base
regions:
  header: Header
  primary_menu: 'Primary menu'
  content: Content
  sidebar: Sidebar
  footer: Footer
screenshot: images/screenshot.png
```

### Libraries

`web/themes/custom/portfolio_theme/portfolio_theme.libraries.yml`
```yaml
base:
  css:
    base:
      css/base/variables.css: {}
      css/base/reset.css: {}
      css/base/typography.css: {}
    layout:
      css/layout/main.css: {}
  js:
    js/portfolio.js: {}
  dependencies:
    - core/drupal
    - core/once
```

### CSS Variables

`web/themes/custom/portfolio_theme/css/base/variables.css`
```css
:root {
  /* Colors */
  --color-primary: #1a1a2e;
  --color-secondary: #16213e;
  --color-accent: #0f3460;
  --color-highlight: #e94560;
  --color-text: #f5f5f5;
  --color-text-muted: #a0a0b0;
  --color-bg: #0d0d1a;
  --color-surface: #1a1a2e;
  --color-border: #2a2a4a;

  /* Typography */
  --font-heading: 'Inter', system-ui, sans-serif;
  --font-body: 'Inter', system-ui, sans-serif;
  --font-mono: 'JetBrains Mono', 'Fira Code', monospace;

  /* Scale */
  --text-xs:   0.75rem;
  --text-sm:   0.875rem;
  --text-base: 1rem;
  --text-lg:   1.125rem;
  --text-xl:   1.25rem;
  --text-2xl:  1.5rem;
  --text-3xl:  1.875rem;
  --text-4xl:  2.25rem;

  /* Spacing */
  --space-1: 0.25rem;
  --space-2: 0.5rem;
  --space-3: 0.75rem;
  --space-4: 1rem;
  --space-6: 1.5rem;
  --space-8: 2rem;
  --space-12: 3rem;
  --space-16: 4rem;

  /* Layout */
  --container-max: 1200px;
  --sidebar-width: 280px;
  --border-radius: 8px;
  --border-radius-lg: 16px;

  /* Transitions */
  --transition-fast: 150ms ease;
  --transition-base: 250ms ease;
}
```

### SDC Components to Build

Build all four components as proper SDC units:

#### `skill-card`

`components/skill-card/skill-card.component.yml`
```yaml
$schema: 'https://git.drupalcode.org/project/drupal/-/raw/HEAD/core/assets/schemas/v1/metadata.schema.json'
name: 'Skill Card'
description: 'Displays a single skill with level indicator.'
props:
  type: object
  properties:
    name:
      type: string
      title: 'Skill name'
    level:
      type: integer
      title: 'Skill level (1-5)'
    category:
      type: string
      title: 'Skill category label'
    icon_class:
      type: string
      title: 'Icon CSS class'
```

`components/skill-card/skill-card.twig`
```twig
<article class="skill-card" data-level="{{ level }}">
  {% if icon_class %}
    <span class="skill-card__icon {{ icon_class }}" aria-hidden="true"></span>
  {% endif %}
  <h3 class="skill-card__name">{{ name }}</h3>
  <span class="skill-card__category">{{ category }}</span>
  <div class="skill-card__level" aria-label="Level {{ level }} of 5">
    {% for i in 1..5 %}
      <span class="skill-card__pip{% if i <= level %} skill-card__pip--filled{% endif %}"></span>
    {% endfor %}
  </div>
</article>
```

`components/skill-card/skill-card.css`
```css
.skill-card {
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--border-radius);
  padding: var(--space-6);
  transition: transform var(--transition-base), border-color var(--transition-base);
}

.skill-card:hover {
  transform: translateY(-2px);
  border-color: var(--color-highlight);
}

.skill-card__name {
  font-size: var(--text-lg);
  font-weight: 600;
  margin: 0 0 var(--space-1);
  color: var(--color-text);
}

.skill-card__category {
  font-size: var(--text-sm);
  color: var(--color-text-muted);
}

.skill-card__level {
  display: flex;
  gap: var(--space-1);
  margin-top: var(--space-3);
}

.skill-card__pip {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: var(--color-border);
}

.skill-card__pip--filled {
  background: var(--color-highlight);
}
```

#### `project-card`

Build `project-card` SDC with props: `title`, `summary`, `thumbnail_url`,
`stack` (array), `status`, `demo_url`, `repo_url`, `path`.

The card displays: thumbnail, title (linked), summary, stack badges,
status badge, and action links.

#### `article-teaser`

Build `article-teaser` SDC with props: `title`, `summary`, `date`,
`tags` (array), `path`, `hero_url` (optional).

#### `feature-badge`

Build `feature-badge` SDC with props: `title`, `pattern`, `difficulty`,
`path`. Compact card for listing feature demos.

### Set as Default Theme

```bash
ddev drush en portfolio_theme -y
ddev drush config-set system.theme default portfolio_theme -y
ddev drush cr
```

### Commit

```bash
git add web/themes/custom/portfolio_theme/
ddev drush cex -y
git add config/sync/
git commit -m "feat: build portfolio_theme with SDC components"
```

**Checkpoint:** Theme active at frontend. SDC components render without
errors. CSS variables load in browser devtools.

---

## Phase 4 — Feature 01: Custom Block Plugin

**Goal:** A `CurrentSkillsBlock` block plugin that queries the `skill`
content type and renders featured skills using the `skill-card` SDC.

**Pattern demonstrated:** Block Plugin, Dependency Injection, Entity Query,
SDC usage from PHP render array.

### Implementation

Create `web/modules/custom/portfolio_core/src/Plugin/Block/CurrentSkillsBlock.php`:

```php
<?php
declare(strict_types=1);

namespace Drupal\portfolio_core\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a block displaying featured skills.
 *
 * @Block(
 *   id = "portfolio_current_skills",
 *   admin_label = @Translation("Portfolio: Current Skills"),
 *   category = @Translation("Portfolio"),
 * )
 */
final class CurrentSkillsBlock extends BlockBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  public static function create(
    ContainerInterface $container,
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
  ): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $nids = $this->entityTypeManager
      ->getStorage('node')
      ->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'skill')
      ->condition('status', 1)
      ->condition('field_featured', 1)
      ->sort('field_skill_level', 'DESC')
      ->execute();

    if (empty($nids)) {
      return [];
    }

    $nodes = $this->entityTypeManager
      ->getStorage('node')
      ->loadMultiple($nids);

    $items = [];
    foreach ($nodes as $node) {
      $category_terms = $node->get('field_category')->referencedEntities();
      $category_label = !empty($category_terms)
        ? $category_terms[0]->label()
        : '';

      $items[] = [
        '#type' => 'component',
        '#component' => 'portfolio_theme:skill-card',
        '#props' => [
          'name'       => $node->label(),
          'level'      => (int) $node->get('field_skill_level')->value,
          'category'   => $category_label,
          'icon_class' => $node->get('field_icon_class')->value ?? '',
        ],
        '#cache' => [
          'tags' => $node->getCacheTags(),
        ],
      ];
    }

    return [
      '#theme_wrappers' => ['container'],
      '#attributes' => ['class' => ['skills-block']],
      'items' => $items,
      '#cache' => [
        'tags' => ['node_list:skill'],
      ],
    ];
  }

}
```

Place the block in a region via `/admin/structure/block` or config.

### Commit

```bash
ddev drush cr
ddev drush cex -y
git add web/modules/custom/portfolio_core/src/Plugin/Block/
git add config/sync/
git commit -m "feat: add CurrentSkillsBlock plugin with SDC render"
```

---

## Phase 5 — Feature 02: Custom REST Resource

**Goal:** A `/api/portfolio/projects` endpoint that returns published
project nodes as JSON with cache metadata.

**Pattern demonstrated:** REST Resource Plugin, CacheableJsonResponse,
Entity Query, JSON API-adjacent pattern.

### Implementation

Create `web/modules/custom/portfolio_core/src/Plugin/rest/resource/ProjectsResource.php`.

The resource must:
- Extend `ResourceBase`.
- Use `EntityTypeManagerInterface` via DI.
- Return an array of projects with: id, title, summary, stack (term labels),
  status (term label), demo_url, repo_url, path.
- Implement `CacheableResponseInterface` using `CacheableJsonResponse`.
- Cache tag: `node_list:project`.
- Expose as GET, no authentication required.

Enable via RestUI at `/admin/config/services/rest`.

Route path: `/api/portfolio/projects`

### Commit

```bash
ddev drush cr
ddev drush cex -y
git add web/modules/custom/portfolio_core/src/Plugin/rest/
git add config/sync/
git commit -m "feat: add Projects REST resource endpoint"
```

---

## Phase 6 — Feature 03: Event Subscriber

> **Status: REMOVED — replaced by standard Drupal path alias behaviour.**
>
> A `ProfileAccessSubscriber` was initially built and then deliberately
> removed. The subscriber redirected `/node/{nid}` → `/about` on every
> request, which duplicated work already handled correctly by pathauto +
> redirect module. The `/about` alias is now created as a `path_alias`
> entity by pathauto (pattern: `/about` for the `profile` content type),
> and the redirect module's `auto_redirect` setting issues canonical 301
> redirects automatically when aliases change.
>
> **Lesson:** Do not build event subscribers to work around missing path
> aliases. Generate the alias first; let the redirect module handle
> canonicalisation.
>
> **Next event subscriber candidate:** A `NodePublishSubscriber` that
> listens on `KernelEvents::RESPONSE` (or a hook_entity_presave equivalent)
> to send a Slack or webhook notification when a `project` node is first
> published. This demonstrates the pattern without duplicating core
> functionality.

---

## Phase 7 — Feature 04: Custom Twig Extension

**Goal:** A `PortfolioTwigExtension` that provides two custom Twig filters:
`skill_level_label` (converts 1–5 to a label) and `reading_time`
(estimates reading time from a string).

**Pattern demonstrated:** Twig Extension, Custom Service, Service tag
`twig.extension`.

### skill_level_label

Input: integer 1–5
Output: Beginner / Elementary / Intermediate / Advanced / Expert

### reading_time

Input: string (HTML body text)
Output: string, e.g., "5 min read" (assumes 200 words per minute,
strips HTML tags before counting).

### Implementation

Create `web/modules/custom/portfolio_core/src/TwigExtension/PortfolioTwigExtension.php`.
Register in `portfolio_core.services.yml` with tag `twig.extension`.

### Usage in Twig

```twig
{{ node.field_skill_level.value | skill_level_label }}
{{ node.body.value | reading_time }}
```

### Commit

```bash
ddev drush cr
git add web/modules/custom/portfolio_core/src/TwigExtension/
git add web/modules/custom/portfolio_core/portfolio_core.services.yml
git commit -m "feat: add PortfolioTwigExtension with skill_level_label and reading_time"
```

---

## Phase 8 — Feature 05: Views + Search API

**Goal:** Faceted project search using Search API with Database backend.

**Pattern demonstrated:** Search API, Facets, Views integration, exposed
filters.

### Steps

1. Create a Search API server using the Database backend.
2. Create a Search API index for `project` nodes.
3. Index fields: title, field_summary, field_stack, field_status.
4. Create a View (`portfolio_projects`) using the Search API index as data source.
5. Add exposed filter for `field_stack` (facet-style).
6. Add exposed filter for `field_status`.
7. Display as a grid using the `project-card` SDC via a custom row plugin
   or via view mode overrides.
8. Path: `/projects`

### View configuration requirements

- Items per page: 12
- Ajax: enabled
- No results text: "No projects found. Try adjusting your filters."
- Sort options: newest first (by `field_start_date`), title A–Z.

### Commit

```bash
ddev drush cex -y
git add config/sync/
git commit -m "feat: configure Search API + Views for project listing with facets"
```

---

## Phase 9 — Feature 06: Node Preprocess + View Mode

**Goal:** Implement `hook_node_view` and preprocess hooks to prepare
structured data for node templates, and create a proper `teaser` view mode
for `project` using the `project-card` SDC.

**Pattern demonstrated:** Preprocess hooks, view modes, render pipeline
integration, SDC from a template.

### Steps

1. Add `teaser` and `full` view modes for `project`, `skill`,
   `article`, and `feature_demo` via config.
2. In `portfolio_core.module`, implement `hook_preprocess_node()` for
   each content type's teaser view mode to prepare clean variables.
3. Create node template overrides in `portfolio_theme/templates/content/`:
    - `node--project--teaser.html.twig`
    - `node--article--teaser.html.twig`
    - `node--feature-demo--teaser.html.twig`
4. Each template should embed the corresponding SDC component with
   the preprocessed variables.

### Commit

```bash
ddev drush cex -y
git add web/modules/custom/portfolio_core/portfolio_core.module
git add web/themes/custom/portfolio_theme/templates/
git add config/sync/
git commit -m "feat: implement node preprocess and SDC-driven view mode templates"
```

---

## Phase 10 — Feature 07: Metatag + Sitemap

**Goal:** Automatic SEO metadata for all content types and an XML sitemap.

**Pattern demonstrated:** Metatag module configuration, Simple Sitemap,
config management for contrib modules.

### Metatag

1. Configure global metatag defaults.
2. Configure per-content-type token-based metatags for `project`, `article`,
   `feature_demo`.
3. Open Graph tags for all published content.

### Simple Sitemap

1. Enable all content types in sitemap.
2. Set `project` and `article` as priority 0.8.
3. Set `feature_demo` as priority 0.7.
4. Exclude `profile` from sitemap (singleton).

### Commit

```bash
ddev drush cex -y
git add config/sync/
git commit -m "feat: configure metatag and simple_sitemap for all content types"
```

---

## Phase 11 — Feature 08: JSON:API Exploration

**Goal:** Document and demonstrate use of Drupal's built-in JSON:API for
consuming project and article data, as a headless pattern demo.

**Pattern demonstrated:** JSON:API, resource filtering, sparse fieldsets,
included relationships.

### Steps

1. Ensure `jsonapi` module is enabled.
2. Create a `feature_demo` node titled "JSON:API Headless Pattern" that
   documents:
    - Endpoint structure: `/jsonapi/node/project`
    - Filter by status: `?filter[status]=1`
    - Sparse fieldset: `?fields[node--project]=title,field_summary`
    - Include relationship: `?include=field_stack`
3. Add a `code_snippet` paragraph with a working `fetch()` example in JS.
4. Add a `key_takeaway` paragraph explaining when to use JSON:API vs
   the custom REST resource from Phase 5.

This phase is documentation-focused. No custom code is required.

### Commit

```bash
git add web/
git commit -m "docs: add JSON:API feature demo node content"
```

---

## Phase 12 — Content Seeding

**Goal:** Realistic demo content so the site presents well.

### Seed this content

**Profile node (1):**
- Name, bio, location, GitHub/LinkedIn URLs.
- Photo (use a placeholder image).
- CV file (create a dummy PDF).

**Skill nodes (10–12):**
- Cover: PHP, Drupal, JavaScript, CSS, MySQL, Git, Docker, Composer,
  REST APIs, Twig, Search API, Site Studio.
- Vary levels 3–5.
- Mark 6 as featured.

**Project nodes (4):**
1. This portfolio site (Drupal 11, Live)
2. A fictional REST API project (PHP, Live)
3. A fictional React app (JavaScript, Archived)
4. A fictional DevOps automation (Docker, Git, Work in Progress)
- Add paragraphs to at least 2 projects.

**Article nodes (3):**
1. "How I set up Drupal 11 with DDEV"
2. "Building reusable SDC components in Drupal 11"
3. "REST vs JSON:API in Drupal: when to use which"
- Use paragraphs (code_snippet, key_takeaway).

**Feature Demo nodes (one per implemented phase):**
- One node per Phase 4–10, each documenting what was built.

### Commit

```bash
git commit -m "chore: seed demo content for all content types"
```

---

## Phase 13 — Deployment Setup

**Goal:** GitHub repository connected to Acquia Cloud (or Platform.sh)
with a working deploy pipeline.

### Option A: Acquia Cloud

1. Install Acquia CLI: https://github.com/acquia/cli
2. Authenticate: `acli auth:login`
3. Link repository: `acli remote:aliases:download`
4. Create `.github/workflows/deploy.yml`:
   ```yaml
   name: Deploy to Acquia
   on:
     push:
       branches: [main]
   jobs:
     deploy:
       runs-on: ubuntu-latest
       steps:
         - uses: actions/checkout@v4
         - name: Deploy
           run: |
             # Acquia Cloud deploy steps
             # drush cim -y && drush updb -y && drush cr
   ```

### Option B: Platform.sh

1. Install Platform CLI: https://docs.platform.sh/administration/cli.html
2. Create `.platform.app.yaml` with Drupal 11 configuration.
3. Create `.platform/routes.yaml` and `.platform/services.yaml`.
4. Connect GitHub repository in Platform.sh dashboard.

### Commit

```bash
git add .github/ .platform*
git commit -m "chore: add deployment pipeline configuration"
```

---

## Feature Inventory

Track all implemented features here. Update after each phase.

| # | Feature | Pattern | Phase | Status |
|---|---------|---------|-------|--------|
| 01 | CurrentSkillsBlock | Block Plugin + DI | 4 | ✅ |
| 02 | Projects REST endpoint | REST Resource Plugin | 5 | ✅ |
| 03 | Profile redirect via Event Subscriber | Event Subscriber | 6 | ❌ removed — pathauto + redirect module used instead |
| 04 | Twig filters (`skill_level_label`, `reading_time`) | Twig Extension | 7 | ✅ |
| 05 | Project search | Search API + Facets | 8 | ✅ |
| 06 | Node view modes — teaser + full | Preprocess hooks + Templates | 9 | ✅ |
| 07 | SEO automation | Metatag + Sitemap | 10 | ✅ |
| 08 | JSON:API demo | Core JSON:API | 11 | ✅ |
| 09 | Project full-page template | `node--project--full.html.twig` + preprocess | — | ✅ |
| 10 | Admin local task tabs | Template override + BEM CSS | — | ✅ |
| 11 | PDF CV export (Phase A) | Entity Print + dompdf + pdf view mode + preprocess + EventSubscriber CSS | — | ✅ |
| 12 | Anonymous PDF access | Role permission config for `entity print access bundle profile` | — | ✅ |
| 13 | ItemList schema on listing pages | `hook_views_post_render` + JSON-LD `ItemList` | — | ✅ |
| 14 | Single-page profile at `/` (hero + anchored sections + contact, sticky section nav with scroll-spy) | Front page = profile node, `node--profile.html.twig`, shared header partial, `social-links` SDC | — | ✅ |
| 15 | Disabled sections (projects, articles, features, skills → 302 `/`; `/about` → 301 `/`) | Config-driven Event Subscriber (`portfolio_core.settings`) + unit tests | — | ✅ |
| 16 | Light/dark switch following OS preference | `theme-toggle` SDC + inline head script + Drupal behavior | — | ✅ |
| 17 | Availability fields (start date, work mode, engagement types, schedule) shown in hero badge + Contact | List/string fields + `ProfileDataService::getAvailability()` + unit tests | — | ✅ |
| 18 | Droopy AI guide — floating mascot answering questions about Ricardo (`POST /api/portfolio/ask`) | SDC with JS + custom JSON endpoint + provider-agnostic LLM chain (Groq → Gemini, free tiers) + flood control + unit tests | — | ✅ |
| 19 | Tailored CV (private admin tool + logged-in `/tailored-cv` Droopy shortcut) | Multi-step Form API + LLM plan with fact-id references + validator + Entity Print render with request-scoped override + unit tests | — | ✅ |

Update status to ✅ after each phase completes and passes checkpoint.

---

## Backlog — Future Features

These are candidates for additional `feature_demo` nodes once the core
build is complete:

- **Form API:** Custom contact form with field validation and a custom
  submit handler that emails and logs the submission.
- **Config Entity:** A custom config entity type for "Social Links" that
  editors can manage via a dedicated UI.
- **Layout Builder:** A landing page content type where Layout Builder is
  enabled and locked to a specific section structure.
- **Custom Migrate:** Import projects from a CSV using Migrate API as a
  demo of data migration patterns.
- **GraphQL:** A GraphQL schema exposing projects and skills, demonstrating
  headless Drupal at a deeper level than JSON:API.
- **Queue API:** A queue worker that processes async tasks (e.g., sends a
  Slack notification when a new project node is published).
- **Batch API:** A drush command that re-generates all pathauto aliases
  using Batch API to avoid timeouts.

---

## Definition of Done (per phase)

A phase is complete when:

- [ ] All code is written and in the correct location.
- [ ] `ddev drush en <modules> -y` passes without errors (if applicable).
- [ ] `ddev drush cr` passes without errors.
- [ ] Feature is visible and functional at https://portfolio.ddev.site.
- [ ] Config has been exported: `ddev drush cex -y`.
- [ ] All changed files staged explicitly (no `git add .`).
- [ ] Commit made with Conventional Commits message.
- [ ] Feature Inventory table updated in this file.
