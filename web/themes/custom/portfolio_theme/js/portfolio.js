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

  Drupal.behaviors.typingEffect = {
    attach(context) {
      once('typing-effect', '.js-typing', context).forEach((el) => {
        const roles = el.dataset.roles.split('|');
        let roleIndex = 0;
        let charIndex = roles[0].length;
        let isDeleting = false;

        function tick() {
          const current = roles[roleIndex];

          if (isDeleting) {
            el.textContent = current.slice(0, charIndex - 1);
            charIndex--;
          } else {
            el.textContent = current.slice(0, charIndex + 1);
            charIndex++;
          }

          let delay = isDeleting ? 35 : 75;

          if (!isDeleting && charIndex === current.length) {
            delay = 2200;
            isDeleting = true;
          } else if (isDeleting && charIndex === 0) {
            isDeleting = false;
            roleIndex = (roleIndex + 1) % roles.length;
            delay = 300;
          }

          setTimeout(tick, delay);
        }

        // Start deleting the initial text so the loop begins naturally.
        setTimeout(tick, 2200);
      });
    },
  };

  Drupal.behaviors.codeHighlight = {
    attach(context) {
      once('code-highlight', '.paragraph--type--code-snippet', context).forEach((block) => {
        const codeEl = block.querySelector('.field--name-field-code');
        if (!codeEl) return;

        // Detect language from the language label field.
        const langEl = block.querySelector('.field--name-field-language');
        const lang = langEl ? langEl.textContent.trim().toLowerCase() : 'none';
        codeEl.innerHTML = `<code class="language-${lang}">${codeEl.textContent}</code>`;

        if (window.Prism) window.Prism.highlightElement(codeEl.querySelector('code'));

        // Copy button.
        const btn = document.createElement('button');
        btn.className = 'code-copy-btn';
        btn.textContent = 'Copy';
        btn.setAttribute('aria-label', Drupal.t('Copy code'));
        block.style.position = 'relative';
        block.appendChild(btn);

        btn.addEventListener('click', () => {
          navigator.clipboard.writeText(codeEl.textContent.trim()).then(() => {
            btn.textContent = 'Copied!';
            setTimeout(() => { btn.textContent = 'Copy'; }, 2000);
          });
        });
      });
    },
  };

  Drupal.behaviors.statsCounter = {
    attach(context) {
      once('stats-counter', '.stats-strip', context).forEach((strip) => {
        const counters = [...strip.querySelectorAll('[data-count]')];

        const run = () => {
          counters.forEach((el) => {
            const target = parseInt(el.dataset.count, 10);
            const duration = 1200;
            const startTime = performance.now();

            const tick = (now) => {
              const progress = Math.min((now - startTime) / duration, 1);
              const ease = 1 - Math.pow(1 - progress, 3);
              el.textContent = Math.floor(ease * target);
              if (progress < 1) requestAnimationFrame(tick);
              else el.textContent = target;
            };

            requestAnimationFrame(tick);
          });
        };

        const observer = new IntersectionObserver((entries) => {
          entries.forEach((entry) => {
            if (entry.isIntersecting) {
              run();
              observer.unobserve(entry.target);
            }
          });
        }, { threshold: 0.5 });

        observer.observe(strip);
      });
    },
  };

  Drupal.behaviors.readingProgress = {
    attach(context) {
      once('reading-progress', '.article-full', context).forEach(() => {
        const bar = document.createElement('div');
        bar.className = 'reading-progress';
        document.body.prepend(bar);

        window.addEventListener('scroll', () => {
          const scrolled = window.scrollY;
          const total = document.documentElement.scrollHeight - window.innerHeight;
          bar.style.width = total > 0 ? `${(scrolled / total) * 100}%` : '0%';
        }, { passive: true });
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
