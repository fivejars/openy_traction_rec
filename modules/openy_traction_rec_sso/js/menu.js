(function ($, Drupal) {

  'use strict';

  // Save page where from was visited sso login page and redirect back to it after login.
  Drupal.behaviors.openy_traction_rec_sso_menu = {
    attach: function (context) {
      $(document).ready(function () {
        // Show user menu/log_in button depend on cookies.
        if (document.cookie.match(/^(.*;)?\s*tr_sso_logged_in\s*=\s*[^;]+(.*)?$/)) {
          $('.tr-sso--logged-in').removeClass('hidden')
        }
        else {
          $('.tr-sso--not-logged-in').removeClass('hidden')
        }
      })
    }
  }

})(jQuery, Drupal);
