(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.locationAutocomplete = {
    attach: function (context, settings) {
      // Find province select field.
      const provinceSelects = once('location-province', '[data-drupal-selector="edit-field-canadian-towns-province"]', context);

      provinceSelects.forEach(function(select) {
        select.addEventListener('change', function(e) {
          const provinceId = e.target.value;

          // Find the city autocomplete field.
          const cityInput = document.querySelector('[data-drupal-selector="edit-field-canadian-towns-city"]');

          if (!cityInput) {
            return;
          }

          // Clear the city field when province changes.
          cityInput.value = '';

          // Update the autocomplete path.
          if (provinceId && provinceId !== '_none' && provinceId !== '') {
            const newPath = '/helper-module/autocomplete/city/' + provinceId;
            cityInput.setAttribute('data-autocomplete-path', newPath);

            // Enable the field.
            cityInput.disabled = false;
            cityInput.placeholder = 'Start typing city name...';
          } else {
            // No province selected, disable autocomplete.
            cityInput.setAttribute('data-autocomplete-path', '');
            cityInput.disabled = true;
            cityInput.placeholder = 'Select a province first';
          }
        });

        // Initialize on page load if editing existing node.
        const currentValue = select.value;
        if (currentValue && currentValue !== '_none' && currentValue !== '') {
          const cityInput = document.querySelector('[data-drupal-selector="edit-field-canadian-towns-city"]');
          if (cityInput) {
            const newPath = '/helper-module/autocomplete/city/' + currentValue;
            cityInput.setAttribute('data-autocomplete-path', newPath);
            cityInput.disabled = false;
            cityInput.placeholder = 'Start typing city name...';
          }
        }
      });
    }
  };

})(Drupal, once);
