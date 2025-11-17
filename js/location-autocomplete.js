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
          // No province selected - disable autocomplete completely.
          cityInput.setAttribute('data-autocomplete-path', '');
          cityInput.disabled = true;
          cityInput.placeholder = 'Select a province first';
          cityInput.value = '';

          // Remove all autocomplete-related attributes and classes.
          cityInput.classList.remove('ui-autocomplete-input');
          cityInput.removeAttribute('autocomplete');
          cityInput.removeAttribute('role');
          cityInput.removeAttribute('aria-autocomplete');
          cityInput.removeAttribute('aria-haspopup');
          cityInput.removeAttribute('aria-expanded');

          // Remove any visible autocomplete dropdown.
          const autocompleteList = document.getElementById(cityInput.id + '-autocomplete');
          if (autocompleteList) {
            autocompleteList.remove();
          }

          // Find and remove any ui-autocomplete elements.
          const uiAutocompleteLists = document.querySelectorAll('.ui-autocomplete');
          uiAutocompleteLists.forEach(function(list) {
            list.remove();
          });
        }
      });
    }
  };

})(Drupal, once);
