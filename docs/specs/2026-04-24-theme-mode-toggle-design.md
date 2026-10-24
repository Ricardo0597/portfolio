# Design Spec: Light/Dark Mode Toggle

**Date:** 2026-04-24
**Branch:** feature/theme-mode-toggle
**Status:** Approved

---

## Overview

Add a light/dark mode toggle to the site header. Dark mode is the default. Light mode uses a Cool Slate palette that shares the same blue undertones as the existing dark navy theme, making the switch feel like a natural flip rather than a completely different theme. The `#e94560` highlight is preserved in both modes for brand consistency.

---

## 1. CSS Token Override

A `[data-theme="light"]` selector block is added to `scss/abstracts/_tokens.scss`, overriding the nine colour custom properties on `:root`. All other tokens (typography, spacing, radius, transitions) are unchanged.

| Token | Dark (default) | Light |
|---|---|---|
| `--color-bg` | `#0d0d1a` | `#f0f4f8` |
| `--color-primary` | `#1a1a2e` | `#ffffff` |
| `--color-secondary` | `#16213e` | `#e8edf4` |
| `--color-accent` | `#0f3460` | `#1e3a5f` |
| `--color-highlight` | `#e94560` | `#e94560` |
| `--color-text` | `#f5f5f5` | `#0d1117` |
| `--color-text-muted` | `#a0a0b0` | `#57606a` |
| `--color-surface` | `#1a1a2e` | `#ffffff` |
| `--color-border` | `#2a2a4a` | `#d8dee4` |

Because all existing components consume these custom properties, no component-level CSS changes are needed.

---

## 2. Toggle Button

A `.theme-toggle` button is added to the end of the nav region in `templates/layout/page.html.twig`, after the existing `{{ page.navigation }}` block.

- **Size:** 32×32px, `border-radius: var(--border-radius)`
- **Style:** Borderless, transparent background matching `.site-nav__link` muted colour; hover state shows a subtle border using `--color-border`
- **Icon:** 🌙 in dark mode, ☀️ in light mode — swapped via JS
- **ARIA:** `aria-label="Switch to light mode"` / `"Switch to dark mode"` updated dynamically on each toggle
- **Placement:** Right end of the header nav, after the nav links

---

## 3. Animations

### Icon spin

The button gets a CSS transition on `transform`. On click, JS adds a `.is-spinning` class that rotates the element 360°, then removes it after the animation completes. The icon character (🌙↔☀️) swaps at the 180° midpoint so the new icon appears as if spinning into view.

```scss
@keyframes spin-once {
  from { transform: rotate(0deg); }
  to   { transform: rotate(360deg); }
}

.theme-toggle.is-spinning {
  animation: spin-once 400ms ease forwards;
}
```

JS listens for `animationend` on the button and removes `.is-spinning` then, so the animation fires once with no reverse snap. The icon character swaps via `setTimeout` at 200ms (the 180° midpoint).

### Page colour fade

`transition: background-color 300ms ease, color 300ms ease, border-color 300ms ease` is applied to `body`, `.site-header`, `footer[role="contentinfo"]`. This covers the primary surfaces; all child elements inherit colour changes through the custom property cascade without needing individual transitions.

---

## 4. JavaScript Behaviour & Persistence

A new `Drupal.behaviors.themeToggle` behaviour is added to the existing `js/portfolio.js`.

**On attach:**
1. Find the `.theme-toggle` button via `once()`.
2. Wire click handler: toggle `data-theme="light"` on `<html>`, update `localStorage('theme')`, update button icon and `aria-label`, trigger spin animation.

**Persistence (no flash on reload):**
A small inline `<script>` is added to `templates/layout/page.html.twig`, placed as the first child of `<html>` before any render. It synchronously reads `localStorage.getItem('theme')` and sets `data-theme` on `<html>` if the value is `'light'`. This runs before CSS paints, preventing a flash of dark→light on page load.

```html
<script>
  if (localStorage.getItem('theme') === 'light') {
    document.documentElement.setAttribute('data-theme', 'light');
  }
</script>
```

---

## Files Changed

| File | Change |
|---|---|
| `scss/abstracts/_tokens.scss` | Add `[data-theme="light"]` colour override block |
| `scss/layout/_main.scss` | Add `.theme-toggle` styles and page-level transition rules |
| `scss/main.scss` | No change needed |
| `css/main.css` | Recompiled output via `ddev theme-build` |
| `templates/layout/page.html.twig` | Add inline anti-flash script and `.theme-toggle` button |
| `js/portfolio.js` | Add `Drupal.behaviors.themeToggle` |

---

## Out of Scope

- Respecting `prefers-color-scheme` OS preference (user toggle overrides only)
- Per-page or per-component mode overrides
- Animating individual component backgrounds beyond the body/header/footer surfaces
