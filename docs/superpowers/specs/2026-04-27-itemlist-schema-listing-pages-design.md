# Design: ItemList Schema Markup on Listing Pages

**Date:** 2026-04-27
**Status:** Implemented — 2026-05-13

---

## Problem

The existing JSON-LD structured data implementation (`hook_page_attachments` in `portfolio_core.module`) only fires on individual node pages. Listing pages (`/projects`, `/skills`) emit no structured data, so Google cannot generate grouped rich result cards for those pages.

Google's structured data guidelines require that markup only describes content visible on the page. This means the ItemList must reflect the View's actual rendered result — respecting pagination and active filters — not a separate entity query.

---

## Scope

- Add `ItemList` schema to `/projects` (view: `portfolio_projects`)
- Add `ItemList` schema to `/skills` (view: `portfolio_skills`)
- Out of scope: front page `WebSite` schema, articles listing (no view exists yet)

---

## Approach

Use `hook_views_post_render(&$view, &$output, &$cache)` in `portfolio_core.module`.

This hook fires after a View executes. `$view->result` contains the exact `ResultRow` objects that were rendered — already filtered, sorted, and paginated. Each row exposes `$row->_entity` (the loaded node), so no second database query is needed.

The JSON-LD block is attached via `$output['#attached']['html_head']`, consistent with the existing node-level implementation.

---

## Schema Structure

### `/projects` → `ItemList` of `SoftwareSourceCode`

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
        "name": "My Project",
        "url": "https://portfolio.ddev.site/projects/my-project",
        "description": "Short summary of the project.",
        "programmingLanguage": ["PHP", "JavaScript"]
      }
    }
  ]
}
```

Fields sourced from the node:
- `name` — node title
- `url` — absolute canonical URL (`$node->toUrl()->setAbsolute()->toString()`)
- `description` — `field_summary` plain text value
- `programmingLanguage` — labels of `field_stack` taxonomy terms (array, omitted if empty)

### `/skills` → `ItemList` of `DefinedTerm`

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

Fields sourced from the node:
- `name` — node title
- `url` — absolute canonical URL

Skills are intentionally minimal — there is no body or summary field worth including at the list level.

---

## Implementation Location

All changes go in `web/modules/custom/portfolio_core/portfolio_core.module`.

Add `hook_views_post_render()` below the existing `hook_page_attachments()`. The hook:

1. Returns immediately for any view ID other than `portfolio_projects` or `portfolio_skills`.
2. Iterates `$view->result`, building a `ListItem` per row (1-based position).
3. Skips rows where `_entity` is not a `NodeInterface` instance (defensive guard).
4. Uses `array_filter` to omit null/empty fields, consistent with existing node-level markup.
5. Attaches the encoded JSON-LD to `$output['#attached']['html_head']` with a unique key per view (`portfolio-json-ld-projects-list`, `portfolio-json-ld-skills-list`).

The `ProfileDataService` is not needed here — no file URL resolution required for listing-level markup.

---

## Key Constraints

- `position` follows `$view->result` order — it mirrors whatever sort the View applies.
- If `$view->result` is empty (no items visible), the hook attaches nothing — an empty `ItemList` is not useful to Google.
- No changes to Views config, routing, or services.yml.
- No new classes, services, or files — only additions to `portfolio_core.module`.

---

## Testing

1. Visit `/projects` — view source — confirm `<script type="application/ld+json">` present in `<head>`.
2. Run `JSON.parse(document.querySelector('script[type="application/ld+json"]').textContent)` in DevTools console — confirm structure matches spec.
3. Apply a facet filter on `/projects` — confirm `itemListElement` count matches visible cards.
4. Visit `/skills` — repeat steps 1–2.
5. Validate both URLs with [Google Rich Results Test](https://search.google.com/test/rich-results) via `ddev share`.
