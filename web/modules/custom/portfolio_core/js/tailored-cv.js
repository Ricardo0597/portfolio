/**
 * @file
 * Live word count for the tailored CV summary.
 */
((Drupal, once) => {
  Drupal.behaviors.portfolioTailoredCvWordCount = {
    attach(context) {
      once('tailored-cv-word-count', '[data-word-count-for]', context).forEach((counter) => {
        const field = document.getElementById(counter.dataset.wordCountFor);
        if (!field) {
          return;
        }
        const update = () => {
          const words = field.value.trim().split(/\s+/).filter(Boolean);
          counter.textContent = words.length;
        };
        field.addEventListener('input', update);
        update();
      });
    },
  };
})(Drupal, once);
