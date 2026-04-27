# ItemList Schema on Listing Pages — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Emit `ItemList` JSON-LD structured data on the `/projects` and `/skills` listing pages using the Views post-render hook, reflecting only the visible rows.

**Architecture:** Add `hook_views_post_render()` to `portfolio_core.module`. The hook reads `$view->result` (exact rendered rows, already filtered and paginated) and attaches a `<script type="application/ld+json">` block to the render array via `$output['#attached']['html_head']`. No new files, services, or classes are needed.

**Tech Stack:** Drupal 11, PHP 8.3, `hook_views_post_render`, `schema.org/ItemList`

---

## File Map

| Action | File | What changes |
|--------|------|--------------|
| Modify | `web/modules/custom/portfolio_core/portfolio_core.module` | Add two `use` statements; add `portfolio_core_views_post_render()` below existing hooks |

---

### Task 1: Implement `hook_views_post_render` for projects and skills listings

**Files:**
- Modify: `web/modules/custom/portfolio_core/portfolio_core.module`

- [ ] **Step 1: Add the missing `use` statements**

Open `web/modules/custom/portfolio_core/portfolio_core.module`. The current `use` block is on lines 9–10:

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

- [ ] **Step 2: Add the hook below `portfolio_core_preprocess_node`**

Append the following function at the end of the file:

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

Expected: `Cache rebuild complete.`

- [ ] **Step 4: Verify projects listing**

Navigate to `https://portfolio.ddev.site/projects` in a browser.

Open DevTools console and run:

```javascript
JSON.parse(document.querySelector('script[type="application/ld+json"]').textContent)
```

Expected output structure:
```json
{
  "@context": "https://schema.org",
  "@type": "ItemList",
  "name": "Projects",
  "itemListElement": [
    { "@type": "ListItem", "position": 1, "item": { "@type": "SoftwareSourceCode", ... } },
    ...
  ]
}
```

Confirm:
- `@type` is `ItemList`
- `itemListElement` length matches the number of project cards visible on the page
- Each item has `name`, `url`, and (if the project has stack terms) `programmingLanguage`

- [ ] **Step 5: Commit**

```bash
git add web/modules/custom/portfolio_core/portfolio_core.module
git commit -m "feat: emit ItemList schema on projects and skills listing pages"
```

---

### Task 2: Verify the skills listing and filter accuracy

**Files:**
- No code changes — verification only

- [ ] **Step 1: Verify skills listing**

Navigate to `https://portfolio.ddev.site/skills` in a browser.

Open DevTools console and run:

```javascript
JSON.parse(document.querySelector('script[type="application/ld+json"]').textContent)
```

Expected output structure:
```json
{
  "@context": "https://schema.org",
  "@type": "ItemList",
  "name": "Skills",
  "itemListElement": [
    { "@type": "ListItem", "position": 1, "item": { "@type": "DefinedTerm", "name": "PHP", "url": "..." } },
    ...
  ]
}
```

Confirm:
- `@type` is `ItemList`
- `itemListElement` length matches the number of skill cards visible
- Each item has `@type: DefinedTerm`, `name`, and `url`

- [ ] **Step 2: Verify filter accuracy on projects**

On `/projects`, apply any available facet filter (e.g. a technology stack term).

Re-run in the console:

```javascript
JSON.parse(document.querySelector('script[type="application/ld+json"]').textContent).itemListElement.length
```

Expected: the count matches the number of project cards visible after filtering — not the total number of projects on the site.

- [ ] **Step 3: Verify no markup on individual node pages is broken**

Navigate to any individual project node (e.g. `/projects/my-project`).

Run in console:

```javascript
JSON.parse(document.querySelector('script[type="application/ld+json"]').textContent)['@type']
```

Expected: `"SoftwareSourceCode"` — the per-node hook is unaffected.

- [ ] **Step 4: Export config (no structural changes, but run as a habit)**

```bash
ddev drush cex -y
```

Expected: `The active configuration is identical to the configuration in the export directory.` (No changes — this hook adds no config.)
