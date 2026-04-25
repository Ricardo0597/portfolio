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
          if (btn.classList.contains('is-spinning')) return;
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
