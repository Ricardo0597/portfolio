# ItemList Schema on Listing Pages — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Emit `ItemList` JSON-LD structured data on `/projects` and `/skills` listing pages, reflecting only the rows actually rendered by the View (already filtered and paginated).

**Architecture:** Add `hook_views_post_render()` to `portfolio_core.module`. The hook reads `$view->result` — the exact `ResultRow` objects already rendered — and attaches a `<script type="application/ld+json">` block to `$output['#attached']['html_head']`. No new files, services, classes, or config changes are needed.

**Tech Stack:** Drupal 11, PHP 8.3, `hook_views_post_render`, `schema.org/ItemList`

---

## File Map

| Action | File | What changes |
|--------|------|--------------|
| Modify | `web/modules/custom/portfolio_core/portfolio_core.module` | Add two `use` statements (lines 9–10); append `portfolio_core_views_post_render()` after line 356 |
| Modify | `PROMPT.md` | Add ItemList schema entry to Feature Inventory table |

---

### Task 1: Implement `hook_views_post_render`

**Files:**
- Modify: `web/modules/custom/portfolio_core/portfolio_core.module`

- [ ] **Step 1: Add the two missing `use` statements**

Open `web/modules/custom/portfolio_core/portfolio_core.module`. The current `use` block at lines 9–10 is:

```php
use Drupal\node\NodeInterface;
use Drupal\Core\Url;
```

Replace it with:

```php
use Drupal\node\NodeInterface;
use Drupal\Core\Url;
use Drupal\views\Plugin\views\cache\CachePluginBase;
use Drupal\views\ViewExecutable;
```

- [ ] **Step 2: Append the hook at the end of the file**

Add the following after the closing brace of `_portfolio_core_preprocess_profile_pdf()` (currently line 356):

```php

/**
 * Implements hook_views_post_render().
 */
function portfolio_core_views_post_render(ViewExecutable $view, array &$output, CachePluginBase $cache): void {
  if (!in_array($view->id(), ['portfolio_projects', 'portfolio_skills'], TRUE)) {
    return;
  }
  if (empty($view->result)) {
    return;
  }

  $items = [];
  $position = 1;
  foreach ($view->result as $row) {
    if (!$row->_entity instanceof NodeInterface) {
      continue;
    }
    $node = $row->_entity;

    if ($view->id() === 'portfolio_projects') {
      $stack = array_map(fn($t) => $t->label(), $node->get('field_stack')->referencedEntities());
      $item = array_filter([
        '@type'               => 'SoftwareSourceCode',
        'name'                => $node->label(),
        'url'                 => $node->toUrl()->setAbsolute()->toString(),
        'description'         => $node->get('field_summary')->value ?? '',
        'programmingLanguage' => $stack ?: NULL,
      ]);
    }
    else {
      $item = [
        '@type' => 'DefinedTerm',
        'name'  => $node->label(),
        'url'   => $node->toUrl()->setAbsolute()->toString(),
      ];
    }

    $items[] = [
      '@type'    => 'ListItem',
      'position' => $position++,
      'item'     => $item,
    ];
  }

  $list_name = $view->id() === 'portfolio_projects' ? 'Projects' : 'Skills';
  $key       = 'portfolio-json-ld-' . str_replace('portfolio_', '', $view->id()) . '-list';

  $output['#attached']['html_head'][] = [
    [
      '#type'       => 'html_tag',
      '#tag'        => 'script',
      '#value'      => json_encode(
        [
          '@context'        => 'https://schema.org',
          '@type'           => 'ItemList',
          'name'            => $list_name,
          'itemListElement' => $items,
        ],
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
      ),
      '#attributes' => ['type' => 'application/ld+json'],
    ],
    $key,
  ];
}
```

- [ ] **Step 3: Rebuild the cache**

```bash
ddev drush cr
```

Expected output: `Cache rebuild complete.`

- [ ] **Step 4: Commit the implementation**

```bash
git add web/modules/custom/portfolio_core/portfolio_core.module
git commit -m "feat: emit ItemList schema on projects and skills listing pages"
```

---

### Task 2: Verify the projects listing

**Files:** No code changes — browser verification only.

- [ ] **Step 1: Check JSON-LD is present on `/projects`**

Navigate to `https://portfolio.ddev.site/projects`. Open DevTools console and run:

```javascript
JSON.parse(document.querySelector('script[type="application/ld+json"]').textContent)
```

Expected structure:
```json
{
  "@context": "https://schema.org",
  "@type": "ItemList",
  "name": "Projects",
  "itemListElement": [
    {
      "@type": "ListItem",
      "position": 1,
      "item": {
        "@type": "SoftwareSourceCode",
        "name": "...",
        "url": "https://portfolio.ddev.site/projects/...",
        "description": "...",
        "programmingLanguage": ["PHP", "..."]
      }
    }
  ]
}
```

Confirm:
- `@type` is `"ItemList"`
- `itemListElement` is not empty
- Each item has `name`, `url`, and (if the project has stack terms) `programmingLanguage`
- `position` values start at 1 and increment

- [ ] **Step 2: Verify filter accuracy**

Apply any available facet filter on `/projects` (e.g. a technology stack term). Re-run:

```javascript
JSON.parse(document.querySelector('script[type="application/ld+json"]').textContent).itemListElement.length
```

Expected: the count matches exactly the number of project cards visible on the page after filtering — not the total project count.

---

### Task 3: Verify the skills listing and regression-check node pages

**Files:** No code changes — browser verification only.

- [ ] **Step 1: Check JSON-LD is present on `/skills`**

Navigate to `https://portfolio.ddev.site/skills`. Open DevTools console and run:

```javascript
JSON.parse(document.querySelector('script[type="application/ld+json"]').textContent)
```

Expected structure:
```json
{
  "@context": "https://schema.org",
  "@type": "ItemList",
  "name": "Skills",
  "itemListElement": [
    {
      "@type": "ListItem",
      "position": 1,
      "item": {
        "@type": "DefinedTerm",
        "name": "PHP",
        "url": "https://portfolio.ddev.site/skills/php"
      }
    }
  ]
}
```

Confirm:
- `@type` is `"ItemList"`
- Each item has `@type: "DefinedTerm"`, `name`, and `url`
- No `description` or `programmingLanguage` fields (skills are intentionally minimal)

- [ ] **Step 2: Regression-check individual node pages**

Navigate to any project node (e.g. `https://portfolio.ddev.site/projects/portfolio-site`). Run:

```javascript
JSON.parse(document.querySelector('script[type="application/ld+json"]').textContent)['@type']
```

Expected: `"SoftwareSourceCode"` — the per-node `hook_page_attachments` markup is unaffected.

---

### Task 4: Update PROMPT.md and finish

**Files:**
- Modify: `PROMPT.md`

- [ ] **Step 1: Update the Feature Inventory table in `PROMPT.md`**

Find the Feature Inventory table and add a new row for this feature. The current last row is:

```
| 12 | Anonymous PDF access | Role permission config for `entity print access bundle profile` | — | ✅ |
```

Add after it:

```
| 13 | ItemList schema on listing pages | `hook_views_post_render` + JSON-LD `ItemList` | — | ✅ |
```

- [ ] **Step 2: Confirm no config changes**

```bash
ddev drush cex -y
```

Expected: `The active configuration is identical to the configuration in the export directory.`
(This hook adds no config — the command is a habit check, not an expectation of changes.)

- [ ] **Step 3: Commit the docs update**

```bash
git add PROMPT.md
git commit -m "docs: update Feature Inventory with ItemList schema listing pages"
```
