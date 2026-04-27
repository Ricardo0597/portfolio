# Theme Mode Toggle Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a dark/light mode toggle button to the site header that persists via localStorage, using a Cool Slate light palette and a spinning icon animation.

**Architecture:** A `[data-theme="light"]` attribute on `<html>` overrides nine CSS custom properties. A Drupal behaviour wires the toggle button, handles localStorage persistence, and triggers a one-shot `@keyframes` spin animation on click. An inline `<script>` injected into `<head>` via `hook_page_attachments()` sets the attribute synchronously before paint to prevent flash on reload.

**Tech Stack:** SCSS, CSS custom properties, vanilla JS (Drupal behaviours + `once()`), Twig, PHP (Drupal 11 theme hook)

---

## File Map

| File | Role |
|---|---|
| `web/themes/custom/portfolio_theme/scss/abstracts/_tokens.scss` | Add `[data-theme="light"]` colour overrides |
| `web/themes/custom/portfolio_theme/scss/layout/_main.scss` | Add `.theme-toggle` styles + page colour-fade transitions |
| `web/themes/custom/portfolio_theme/css/main.css` | Recompiled output — do not edit directly |
| `web/themes/custom/portfolio_theme/templates/layout/page.html.twig` | Add `.theme-toggle` button in header |
| `web/themes/custom/portfolio_theme/portfolio_theme.theme` | Add `portfolio_theme_page_attachments()` for anti-flash script |
| `web/themes/custom/portfolio_theme/js/portfolio.js` | Add `Drupal.behaviors.themeToggle` |

---

## Task 1: Light mode CSS tokens

**Files:**
- Modify: `web/themes/custom/portfolio_theme/scss/abstracts/_tokens.scss`

- [ ] **Step 1: Add the `[data-theme="light"]` override block at the end of `_tokens.scss`**

Append after the closing `}` of the existing `:root` block:

```scss
[data-theme='light'] {
  --color-bg:         #f0f4f8;
  --color-primary:    #ffffff;
  --color-secondary:  #e8edf4;
  --color-accent:     #1e3a5f;
  --color-highlight:  #e94560;
  --color-text:       #0d1117;
  --color-text-muted: #57606a;
  --color-surface:    #ffffff;
  --color-border:     #d8dee4;
}
```

- [ ] **Step 2: Commit**

```bash
git add web/themes/custom/portfolio_theme/scss/abstracts/_tokens.scss
git commit -m "feat: add light mode CSS token overrides via data-theme attribute"
```

---

## Task 2: Toggle button styles and colour-fade transitions

**Files:**
- Modify: `web/themes/custom/portfolio_theme/scss/layout/_main.scss`

- [ ] **Step 1: Add colour-fade transitions to page surfaces**

In `_main.scss`, add `transition` declarations to `body` and `.site-header`. Find the `.site-header` block (around line 34) and add the transition to it. Also add a standalone `body` rule before the `.layout-container` block. The full additions:

```scss
// ─── Mode-switch colour fade ───────────────────────────────────────────────

body {
  transition:
    background-color 300ms ease,
    color 300ms ease;
}

footer[role='contentinfo'] {
  transition:
    background-color 300ms ease,
    border-color 300ms ease;
}
```

And inside the existing `.site-header` rule, add the transition:

```scss
.site-header {
  background: var(--color-primary);
  border-bottom: 1px solid var(--color-border);
  padding: var(--space-3) 0;
  position: sticky;
  top: 0;
  z-index: 100;
  transition: background-color 300ms ease, border-color 300ms ease; // ← add this line
  // ... rest of existing rules unchanged
}
```

- [ ] **Step 2: Add `.theme-toggle` button styles**

Append a new section at the end of `_main.scss`:

```scss
// ─── Theme mode toggle ────────────────────────────────────────────────────────

@keyframes spin-once {
  from { transform: rotate(0deg); }
  to   { transform: rotate(360deg); }
}

.theme-toggle {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 32px;
  height: 32px;
  padding: 0;
  background: transparent;
  border: 1px solid transparent;
  border-radius: var(--border-radius);
  color: var(--color-text-muted);
  font-size: var(--text-lg);
  cursor: pointer;
  flex-shrink: 0;
  line-height: 1;
  transition: border-color var(--transition-fast), color var(--transition-fast);

  &:hover {
    border-color: var(--color-border);
    color: var(--color-text);
  }

  &.is-spinning {
    animation: spin-once 400ms ease forwards;
  }
}
```

- [ ] **Step 3: Commit**

```bash
git add web/themes/custom/portfolio_theme/scss/layout/_main.scss
git commit -m "feat: add theme toggle button styles and colour-fade transitions"
```

---

## Task 3: Anti-flash script via theme hook

**Files:**
- Modify: `web/themes/custom/portfolio_theme/portfolio_theme.theme`

- [ ] **Step 1: Add `portfolio_theme_page_attachments()` to inject the script into `<head>`**

Open `portfolio_theme.theme` and append this function:

```php
/**
 * Implements hook_page_attachments().
 */
function portfolio_theme_page_attachments(array &$attachments): void {
  $attachments['#attached']['html_head'][] = [
    [
      '#type' => 'html_tag',
      '#tag' => 'script',
      '#value' => "if(localStorage.getItem('theme')==='light'){document.documentElement.setAttribute('data-theme','light');}",
    ],
    'portfolio-theme-mode-init',
  ];
}
```

This runs synchronously in `<head>` before CSS paints, preventing a dark→light flash on page load for users who have switched to light mode.

- [ ] **Step 2: Commit**

```bash
git add web/themes/custom/portfolio_theme/portfolio_theme.theme
git commit -m "feat: inject theme mode init script into <head> to prevent flash on reload"
```

---

## Task 4: Toggle button in page template

**Files:**
- Modify: `web/themes/custom/portfolio_theme/templates/layout/page.html.twig`

- [ ] **Step 1: Add the toggle button to the header**

Replace the entire file content with:

```twig
<a href="#main-content" class="skip-link visually-hidden focusable">Skip to main content</a>

<header role="banner" class="site-header">
  <div class="layout-container site-header__inner">
    {{ page.header }}
    <button class="theme-toggle"
            type="button"
            aria-label="{{ 'Switch to light mode'|t }}">🌙</button>
  </div>
</header>

<main id="main-content" role="main" class="site-main">
  <div class="layout-container">
    {{ page.content }}
  </div>
</main>

<footer role="contentinfo" class="site-footer">
  <div class="layout-container site-footer__inner">
    <a href="{{ path('<front>') }}" class="site-footer__brand" rel="home" aria-label="{{ 'Home'|t }}">
      <img class="site-footer__logo"
           src="{{ logo }}"
           alt="{{ 'Site logo'|t }}"
           loading="lazy">
    </a>
    <span class="site-footer__copy">
      &copy; {{ "now"|date("Y") }} Ricardo Garcia
    </span>
    {{ page.footer }}
  </div>
</footer>
```

- [ ] **Step 2: Commit**

```bash
git add web/themes/custom/portfolio_theme/templates/layout/page.html.twig
git commit -m "feat: add theme toggle button to site header"
```

---

## Task 5: JavaScript behaviour

**Files:**
- Modify: `web/themes/custom/portfolio_theme/js/portfolio.js`

- [ ] **Step 1: Add `Drupal.behaviors.themeToggle` to `portfolio.js`**

Replace the entire file content with:

```javascript
(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.portfolioNav = {
    attach(context) {
      once('portfolio-nav', '.site-nav__link', context).forEach((link) => {
        if (link.href === window.location.href) {
          link.classList.add('site-nav__link--active');
        }
      });
    },
  };

  Drupal.behaviors.themeToggle = {
    attach(context) {
      once('theme-toggle', '.theme-toggle', context).forEach((btn) => {
        const html = document.documentElement;

        function isLight() {
          return html.getAttribute('data-theme') === 'light';
        }

        function updateButton() {
          btn.textContent = isLight() ? '☀️' : '🌙';
          btn.setAttribute(
            'aria-label',
            isLight() ? Drupal.t('Switch to dark mode') : Drupal.t('Switch to light mode')
          );
        }

        // Sync button state with current theme on attach.
        updateButton();

        btn.addEventListener('click', () => {
          const switchingToLight = !isLight();

          // Start spin animation.
          btn.classList.add('is-spinning');

          // Swap icon and apply theme at the 180° midpoint (200ms into 400ms).
          setTimeout(() => {
            if (switchingToLight) {
              html.setAttribute('data-theme', 'light');
              localStorage.setItem('theme', 'light');
            } else {
              html.removeAttribute('data-theme');
              localStorage.setItem('theme', 'dark');
            }
            updateButton();
          }, 200);

          // Remove spin class after animation completes (no reverse snap).
          btn.addEventListener('animationend', () => {
            btn.classList.remove('is-spinning');
          }, { once: true });
        });
      });
    },
  };

}(Drupal, once));
```

- [ ] **Step 2: Commit**

```bash
git add web/themes/custom/portfolio_theme/js/portfolio.js
git commit -m "feat: add themeToggle Drupal behaviour with spin animation and localStorage persistence"
```

---

## Task 6: Compile and verify

**Files:**
- Modify: `web/themes/custom/portfolio_theme/css/main.css` (compiled output)

- [ ] **Step 1: Compile SCSS**

```bash
ddev theme-build
```

Expected: exits 0, no errors. `css/main.css` is updated.

- [ ] **Step 2: Clear Drupal cache**

```bash
ddev drush cr
```

Expected: `Cache rebuild complete.`

- [ ] **Step 3: Verify visually**

Open the site and check:

1. **Default state:** Page loads in dark mode. Header shows 🌙 button at the far right of the nav.
2. **Toggle to light:** Click 🌙. Button spins 360°, icon swaps to ☀️ at halfway through the spin. Page background, header, and footer transition smoothly to the Cool Slate light palette over 300ms.
3. **Toggle back:** Click ☀️. Same spin animation, page returns to dark.
4. **Persistence:** Switch to light mode, reload the page. Page loads in light mode with no dark flash.
5. **Persistence reset:** Open DevTools → Application → Local Storage, delete the `theme` key, reload. Page loads in dark mode.
6. **ARIA:** Inspect the button in DevTools. `aria-label` reads "Switch to light mode" in dark mode and "Switch to dark mode" in light mode.

- [ ] **Step 4: Commit compiled CSS**

```bash
git add web/themes/custom/portfolio_theme/css/main.css
git commit -m "chore: recompile CSS with light mode tokens and theme toggle styles"
```
