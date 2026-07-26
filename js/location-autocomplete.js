(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.locationAutocomplete = {
    attach: function (context, settings) {
      // Update autocomplete path when city field is attached/reattached.
      const cityInputs = once('city-autocomplete-init', '[data-drupal-selector="edit-field-canadian-towns-city"]', context);

      cityInputs.forEach(function(cityInput) {
        const provinceId = cityInput.getAttribute('data-province-id');

        // Check if province is valid (not empty, not 0, not _none).
        if (provinceId && provinceId !== '0' && provinceId !== '_none' && provinceId !== '') {
          // Set the autocomplete path based on the province ID.
          const autocompletePath = '/helper-module/autocomplete/city/' + provinceId;
          cityInput.setAttribute('data-autocomplete-path', autocompletePath);

          // Enable the field.
          cityInput.disabled = false;
          cityInput.placeholder = 'Start typing city name...';
        } else {
          // No province selected - remove autocomplete path completely.
          cityInput.removeAttribute('data-autocomplete-path');
          cityInput.disabled = true;
          cityInput.placeholder = 'Select a province first';
          cityInput.value = '';
        }
      });
    }
  };

})(Drupal, once);
