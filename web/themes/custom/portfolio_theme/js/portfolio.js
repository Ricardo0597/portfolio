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

}(Drupal, once));
