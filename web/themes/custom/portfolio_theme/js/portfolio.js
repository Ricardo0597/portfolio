(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.pageTransition = {
    attach(context) {
      once('page-transition', 'body', context).forEach((body) => {
        body.style.opacity = '1';
      });
    },
  };

  Drupal.behaviors.portfolioNav = {
    attach(context) {
      once('portfolio-nav', '.site-nav__link', context).forEach((link) => {
        if (link.href === window.location.href) {
          link.classList.add('site-nav__link--active');
        }
      });
    },
  };

  Drupal.behaviors.scrollReveal = {
    attach(context) {
      const selectors = [
        '.project-card',
        '.article-teaser',
        '.skill-card',
        '.about__section',
        '.about__work-item',
        '.about__edu-item',
      ].join(',');

      const targets = context === document
        ? [...document.querySelectorAll(selectors)]
        : [...context.querySelectorAll(selectors)];

      if (!targets.length) return;

      if (!('IntersectionObserver' in window)) {
        targets.forEach((el) => el.classList.add('js-reveal', 'is-visible'));
        return;
      }

      const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) {
            entry.target.classList.add('is-visible');
            observer.unobserve(entry.target);
          }
        });
      }, { threshold: 0.1 });

      targets.forEach((el) => {
        el.classList.add('js-reveal');
        observer.observe(el);
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
            } else {
              html.removeAttribute('data-theme');
            }
            const themeValue = switchingToLight ? 'light' : 'dark';
            try { localStorage.setItem('theme', themeValue); } catch (e) {}
            try {
              document.cookie = 'theme=' + themeValue + ';path=/;max-age=31536000;SameSite=Lax';
            } catch (e) {}
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
