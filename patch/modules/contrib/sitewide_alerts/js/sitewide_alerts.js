(function ($, Drupal) {
  Drupal.behaviors.sitewide_alerts = {
    attach: function (context, drupalSettings) {

      if (drupalSettings.sitewide_alerts && drupalSettings.sitewide_alerts.dismissedKeys && drupalSettings.sitewide_alerts.cookieExpiration) {

        // Since the key is updated every time the configuration form is saved,
        // we can ensure users don't miss newly added or changed alerts.
        let dismissedKeys = drupalSettings.sitewide_alerts.dismissedKeys;
        let dismissedKeysCookie = $.cookie('Drupal.visitor.sitewide_alerts_dismissed');
        let userDismissedKeys = dismissedKeysCookie ? JSON.parse(dismissedKeysCookie) : Array();

        // Loop through each site alert
        $('.c-site-alert').each(function () {
          let $siteAlert = $(this);
          let showAlert = true;
          $.each(userDismissedKeys, function (indexKey, dismissedKey) {
            if (dismissedKey === $siteAlert.attr('data-dismiss-key')) {
              showAlert = false;
            }
          });
          if (showAlert) {
            $siteAlert.show();
            // Trigger custom event
            $.event.trigger({
              type: "onSiteAlertShow",
              siteAlert: $siteAlert
            });
          }
        });

        // Close event or site alert click event
        $('.c-site-alert .c-site-alert__close', context).once('sitewide_alerts').on('click', function (e) {

          // Do not perform default action.
          e.preventDefault();

          // Get site alert
          let $siteAlert = $(this).closest('.c-site-alert');

          // Add dismissed key to cookie
          userDismissedKeys.push($siteAlert.attr('data-dismiss-key'));
          var options = {};
          var expiration = drupalSettings.sitewide_alerts.cookieExpiration;
          // If the expiration value is "default", we don't need to set the expires property
          // as $.cookie will default to a session based cookie.
          if (expiration !== 'default') {
            options.expires = expiration;
          }
          options.path = drupalSettings.path.baseUrl;
          $.cookie('Drupal.visitor.sitewide_alerts_dismissed', JSON.stringify(userDismissedKeys), options);

          // Remove alert
          if ($siteAlert.hasClass('position-top')) {
            $siteAlert.slideUp('normal', function () {
              $(this).remove();
              // Trigger custom event
              $.event.trigger({
                type: "onSiteAlertClose",
                siteAlert: $siteAlert
              });
            });
          }
          else {
            $siteAlert.slideDown('normal', function () {
              $(this).remove();
              // Trigger custom event
              $.event.trigger({
                type: "onSiteAlertClose",
                siteAlert: $siteAlert
              });
            });
          }
        });
      }
    }
  }
})(jQuery, Drupal);
