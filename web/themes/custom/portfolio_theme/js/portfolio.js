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

  Drupal.behaviors.projectFilter = {
    attach(context) {
      once('project-filter', '.views-element-container', context).forEach((container) => {
        const cards = [...container.querySelectorAll('.project-card')];
        if (!cards.length) return;

        // Hide the server-side exposed filter form — pills replace it.
        const exposedForm = container.querySelector('.views-exposed-form');
        if (exposedForm) exposedForm.hidden = true;

        const stacks = new Set();
        const statuses = new Set();

        cards.forEach((card) => {
          (card.dataset.stack || '').split('|').filter(Boolean).forEach((t) => stacks.add(t));
          if (card.dataset.status) statuses.add(card.dataset.status);
        });

        if (!stacks.size && !statuses.size) return;

        const bar = document.createElement('div');
        bar.className = 'project-filter';
        bar.setAttribute('role', 'group');
        bar.setAttribute('aria-label', Drupal.t('Filter projects'));

        function makeBtn(label, attr, value) {
          const btn = document.createElement('button');
          btn.type = 'button';
          btn.className = 'project-filter__btn';
          btn.textContent = label;
          btn.dataset.filterAttr = attr;
          btn.dataset.filterValue = value;
          return btn;
        }

        const allBtn = makeBtn(Drupal.t('All'), '', '');
        allBtn.classList.add('is-active');
        bar.appendChild(allBtn);

        statuses.forEach((s) => {
          bar.appendChild(makeBtn(s.replace(/-/g, ' '), 'status', s));
        });

        stacks.forEach((t) => {
          bar.appendChild(makeBtn(t, 'stack', t));
        });

        // Insert pill bar before the first project row.
        const firstRow = container.querySelector('.views-row');
        container.insertBefore(bar, firstRow);

        // Intercept card-level pill links and delegate to the filter bar.
        container.addEventListener('click', (e) => {
          const pill = e.target.closest('[data-filter-attr]');
          if (!pill || pill.classList.contains('project-filter__btn')) return;
          e.preventDefault();
          const { filterAttr, filterValue } = pill.dataset;
          const match = [...bar.querySelectorAll('.project-filter__btn')]
            .find((b) => b.dataset.filterAttr === filterAttr && b.dataset.filterValue === filterValue);
          if (match) match.click();
        });

        // Screen-reader live region — announces filter result counts.
        const liveRegion = document.createElement('div');
        liveRegion.setAttribute('role', 'status');
        liveRegion.setAttribute('aria-live', 'polite');
        liveRegion.setAttribute('aria-atomic', 'true');
        liveRegion.className = 'visually-hidden';
        container.appendChild(liveRegion);

        // Empty-state element shown when no cards match the active filter.
        const emptyMsg = document.createElement('p');
        emptyMsg.className = 'project-filter-empty';
        emptyMsg.textContent = Drupal.t('No projects match this filter.');
        emptyMsg.hidden = true;
        container.appendChild(emptyMsg);

        bar.addEventListener('click', (e) => {
          const btn = e.target.closest('.project-filter__btn');
          if (!btn) return;

          [...bar.querySelectorAll('.project-filter__btn')].forEach((b) => b.classList.remove('is-active'));
          btn.classList.add('is-active');

          const { filterAttr, filterValue } = btn.dataset;

          cards.forEach((card) => {
            if (!filterAttr) {
              card.classList.remove('is-hidden');
              return;
            }
            const haystack = filterAttr === 'stack'
              ? (card.dataset.stack || '').split('|')
              : [card.dataset.status || ''];
            card.classList.toggle('is-hidden', !haystack.includes(filterValue));
          });

          const visible = cards.filter((c) => !c.classList.contains('is-hidden')).length;
          emptyMsg.hidden = visible > 0;
          liveRegion.textContent = visible > 0
            ? Drupal.t('@count projects shown', { '@count': visible })
            : Drupal.t('No projects match this filter.');
        });
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
