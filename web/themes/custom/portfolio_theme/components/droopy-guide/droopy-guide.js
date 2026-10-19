/**
 * @file
 * Droopy Guide: animated mascot + chat panel backed by /api/portfolio/ask.
 */
((Drupal, once) => {
  const HISTORY_KEY = 'droopy:history';
  const SEEN_KEY = 'droopy:seen';
  const MAX_HISTORY = 6;
  const SECTIONS = ['about', 'skills', 'experience', 'education', 'contact'];

  /**
   * sessionStorage helpers that never throw.
   */
  const store = {
    get(key, fallback) {
      try {
        const raw = sessionStorage.getItem(key);
        return raw ? JSON.parse(raw) : fallback;
      } catch (e) {
        return fallback;
      }
    },
    set(key, value) {
      try {
        sessionStorage.setItem(key, JSON.stringify(value));
      } catch (e) {
        // Storage unavailable (private mode, blocked): degrade silently.
      }
    },
  };

  Drupal.behaviors.droopyGuide = {
    attach(context) {
      once('droopy', '.droopy', context).forEach((root) => {
        const launcher = root.querySelector('.droopy__launcher');
        const panel = root.querySelector('.droopy__panel');
        const bubble = root.querySelector('.droopy__bubble');
        const log = root.querySelector('.droopy__log');
        const chips = root.querySelector('.droopy__chips');
        const form = root.querySelector('.droopy__form');
        const input = root.querySelector('.droopy__input');
        const send = root.querySelector('.droopy__send');
        const eyes = root.querySelector('.droopy__eyes');
        const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
        const phone = window.matchMedia('(max-width: 767px)');
        const defaultPlaceholder = input.placeholder;

        let mode = 'chat';
        let busy = false;
        let history = store.get(HISTORY_KEY, []);
        const seen = new Set(store.get(SEEN_KEY, []));

        const isOpen = () => root.classList.contains('is-open');

        // ── Speech bubble ───────────────────────────────────────────

        let bubbleTimer = null;
        let typingTimer = null;

        function say(id, text, duration = 8000) {
          if (isOpen() || seen.has(id)) return;
          seen.add(id);
          store.set(SEEN_KEY, [...seen]);
          clearTimeout(bubbleTimer);
          clearInterval(typingTimer);
          bubble.classList.add('is-visible');
          if (reducedMotion.matches) {
            bubble.textContent = text;
          } else {
            const chars = [...text];
            let i = 0;
            bubble.textContent = '';
            typingTimer = setInterval(() => {
              bubble.textContent += chars[i];
              i += 1;
              if (i >= chars.length) clearInterval(typingTimer);
            }, 35);
          }
          bubbleTimer = setTimeout(() => bubble.classList.remove('is-visible'), duration);
        }

        setTimeout(() => say('greeting', Drupal.t('Ask me about Ricardo 👋')), 3000);
        setTimeout(() => say('available', Drupal.t('Ricardo is available now — ask me how he can help')), 25000);

        const experience = document.getElementById('experience');
        if (experience && 'IntersectionObserver' in window) {
          const observer = new IntersectionObserver((entries) => {
            if (entries.some((entry) => entry.isIntersecting)) {
              say('experience', Drupal.t('Want the 30-second version of his experience?'));
              observer.disconnect();
            }
          }, { threshold: 0.2 });
          observer.observe(experience);
        }

        // ── Eyes, blinking, sleeping ───────────────────────────────

        let pointerFrame = null;
        window.addEventListener('pointermove', (e) => {
          if (reducedMotion.matches || pointerFrame) return;
          pointerFrame = requestAnimationFrame(() => {
            pointerFrame = null;
            const rect = launcher.getBoundingClientRect();
            const dx = e.clientX - (rect.left + rect.width / 2);
            const dy = e.clientY - (rect.top + rect.height / 2);
            const distance = Math.hypot(dx, dy) || 1;
            const reach = Math.min(1, distance / 300) * 6;
            eyes.style.transform = `translate(${(dx / distance) * reach}px, ${(dy / distance) * reach}px)`;
          });
        }, { passive: true });

        function blink() {
          root.classList.add('is-blinking');
          setTimeout(() => root.classList.remove('is-blinking'), 200);
        }

        (function scheduleBlink() {
          setTimeout(() => {
            if (!reducedMotion.matches && !root.classList.contains('is-sleeping')) blink();
            scheduleBlink();
          }, 4000 + Math.random() * 3000);
        }());

        let idleTimer = null;
        function resetIdle() {
          root.classList.remove('is-sleeping');
          clearTimeout(idleTimer);
          idleTimer = setTimeout(() => {
            if (!isOpen()) root.classList.add('is-sleeping');
          }, 60000);
        }
        ['pointermove', 'keydown', 'scroll', 'touchstart'].forEach((type) => {
          window.addEventListener(type, resetIdle, { passive: true });
        });
        resetIdle();

        // A slow blink when the theme switches.
        new MutationObserver(() => {
          if (reducedMotion.matches) return;
          root.classList.add('is-blinking');
          setTimeout(() => root.classList.remove('is-blinking'), 600);
        }).observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme'] });

        // ── Panel open / close ─────────────────────────────────────

        function open() {
          root.classList.add('is-open');
          panel.hidden = false;
          launcher.setAttribute('aria-expanded', 'true');
          bubble.classList.remove('is-visible');
          if (!reducedMotion.matches) {
            root.classList.add('is-hello');
            setTimeout(() => root.classList.remove('is-hello'), 500);
          }
          log.scrollTop = log.scrollHeight;
          input.focus();
        }

        function close() {
          root.classList.remove('is-open');
          panel.hidden = true;
          launcher.setAttribute('aria-expanded', 'false');
          launcher.focus();
        }

        launcher.addEventListener('click', () => (isOpen() ? close() : open()));
        root.querySelector('.droopy__close').addEventListener('click', close);
        root.addEventListener('keydown', (e) => {
          if (e.key === 'Escape' && isOpen()) {
            e.stopPropagation();
            close();
          }
        });

        // ── Messages ───────────────────────────────────────────────

        function addMessage(role, text) {
          const p = document.createElement('p');
          p.className = `droopy__msg droopy__msg--${role === 'user' ? 'user' : 'bot'}`;
          // Always text, never HTML: answers come from an LLM.
          p.textContent = text;
          log.appendChild(p);
          log.scrollTop = log.scrollHeight;
          return p;
        }

        function scrollToSection(id) {
          const section = document.getElementById(id);
          if (!section) return;
          section.scrollIntoView({ behavior: reducedMotion.matches ? 'auto' : 'smooth' });
          section.classList.add('droopy-highlight');
          setTimeout(() => section.classList.remove('droopy-highlight'), 1500);
          if (phone.matches) close();
        }

        const actionLabels = {
          about: Drupal.t('See About'),
          skills: Drupal.t('See skills'),
          experience: Drupal.t('See experience'),
          education: Drupal.t('See education'),
          contact: Drupal.t('See contact'),
        };

        function addActions(actions) {
          const valid = (actions || []).filter((a) => a && (
            (a.type === 'scroll' && SECTIONS.includes(a.target)) || a.type === 'cv' || a.type === 'email'
          ));
          if (!valid.length) return;
          const wrap = document.createElement('div');
          wrap.className = 'droopy__actions';
          valid.forEach((action) => {
            let el;
            if (action.type === 'scroll') {
              el = document.createElement('button');
              el.type = 'button';
              el.textContent = actionLabels[action.target];
              el.addEventListener('click', () => scrollToSection(action.target));
            } else if (action.type === 'cv' && root.dataset.cvUrl) {
              el = document.createElement('a');
              el.href = root.dataset.cvUrl;
              el.target = '_blank';
              el.rel = 'noopener';
              el.textContent = Drupal.t('Download CV (PDF)');
            } else if (action.type === 'email' && root.dataset.email) {
              el = document.createElement('a');
              el.href = `mailto:${root.dataset.email}`;
              el.textContent = Drupal.t('Email Ricardo');
            }
            if (el) {
              el.className = 'droopy__action';
              wrap.appendChild(el);
            }
          });
          if (wrap.children.length) {
            log.appendChild(wrap);
            log.scrollTop = log.scrollHeight;
          }
        }

        function remember(role, content) {
          history.push({ role, content: content.slice(0, 1000) });
          history = history.slice(-MAX_HISTORY);
          store.set(HISTORY_KEY, history);
        }

        // Restore the conversation from this browser session.
        if (history.length) {
          history.forEach((turn) => addMessage(turn.role, turn.content));
          if (chips) chips.hidden = true;
        }

        function setMode(next) {
          mode = next;
          root.classList.toggle('is-fit', mode === 'fit');
          input.maxLength = mode === 'fit' ? 6000 : 1000;
          input.placeholder = mode === 'fit' ? Drupal.t('Paste the job description…') : defaultPlaceholder;
        }

        // ── Asking ─────────────────────────────────────────────────

        async function ask(question) {
          if (busy || !question.trim()) return;
          busy = true;
          send.disabled = true;
          if (chips) chips.hidden = true;
          root.classList.remove('is-error');

          const display = mode === 'fit' ? Drupal.t('Here is the job description — is Ricardo a fit?') : question;
          addMessage('user', display);
          input.value = '';

          const typing = document.createElement('div');
          typing.className = 'droopy__typing';
          typing.setAttribute('aria-label', Drupal.t('Droopy is thinking'));
          typing.innerHTML = '<span></span><span></span><span></span>';
          log.appendChild(typing);
          log.scrollTop = log.scrollHeight;
          root.classList.add('is-thinking');

          const requestMode = mode;
          const priorHistory = history.slice();
          let data = null;
          try {
            const response = await fetch(root.dataset.endpoint, {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({ question, mode: requestMode, history: priorHistory }),
            });
            data = await response.json();
          } catch (e) {
            data = null;
          }

          typing.remove();
          root.classList.remove('is-thinking');

          if (data && typeof data.answer === 'string' && data.answer) {
            addMessage('bot', data.answer);
            addActions(data.actions);
            if (data.provider === 'none') root.classList.add('is-error');
            remember('user', display);
            remember('assistant', data.answer);
          } else {
            root.classList.add('is-error');
            addMessage('bot', Drupal.t("I'm napping right now 😴 — email Ricardo instead and he'll get back to you."));
            addActions([{ type: 'email' }]);
          }

          setMode('chat');
          busy = false;
          send.disabled = false;
          input.focus();
        }

        form.addEventListener('submit', (e) => {
          e.preventDefault();
          ask(input.value);
        });

        // Enter sends, Shift+Enter adds a new line.
        input.addEventListener('keydown', (e) => {
          if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            ask(input.value);
          }
        });

        if (chips) {
          chips.addEventListener('click', (e) => {
            const chip = e.target.closest('.droopy__chip');
            if (!chip) return;
            if (chip.dataset.mode === 'fit') {
              setMode('fit');
              input.focus();
              return;
            }
            ask(chip.textContent.trim());
          });
        }
      });
    },
  };
})(Drupal, once);
