(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.locationAutocomplete = {
    attach: function (context, settings) {
      // Find province field - works for both create and edit forms.
      const provinceSelects = once('location-province', 'select[name*="[field_province]"]', context);

      provinceSelects.forEach(function(select) {
        select.addEventListener('change', function(e) {
          const provinceId = e.target.value;

          // Find the city autocomplete field.
          const cityInput = document.querySelector('input[name*="[field_city][0][target_id]"]');

          if (!cityInput) {
            return;
          }

          // Clear the city field when province changes.
          cityInput.value = '';

          // Update the autocomplete path.
          if (provinceId && provinceId !== '_none') {
            const newPath = '/helper-module/autocomplete/city/' + provinceId;
            cityInput.setAttribute('data-autocomplete-path', newPath);

            // Enable the field.
            cityInput.disabled = false;
            cityInput.placeholder = 'Start typing city name...';

            // Reinitialize Drupal autocomplete with new path.
            if (Drupal.autocomplete) {
              // Remove old autocomplete instance if exists.
              const autocompleteInstance = cityInput.autocomplete;
              if (autocompleteInstance && typeof autocompleteInstance.destroy === 'function') {
                autocompleteInstance.destroy();
              }

              // Reattach autocomplete with new path.
              Drupal.autocomplete.attach(cityInput);
            }
          } else {
            // No province selected, disable autocomplete.
            cityInput.setAttribute('data-autocomplete-path', '');
            cityInput.disabled = true;
            cityInput.placeholder = 'Select a province first';
          }
        });

        // Initialize on page load if editing existing node.
        const currentValue = select.value;
        if (currentValue && currentValue !== '_none') {
          const cityInput = document.querySelector('input[name*="[field_city][0][target_id]"]');
          if (cityInput) {
            const newPath = '/helper-module/autocomplete/city/' + currentValue;
            cityInput.setAttribute('data-autocomplete-path', newPath);
            cityInput.disabled = false;
            cityInput.placeholder = 'Start typing city name...';
          }
        } else {
          // Disable city field if no province selected.
          const cityInput = document.querySelector('input[name*="[field_city][0][target_id]"]');
          if (cityInput && !cityInput.value) {
            cityInput.disabled = true;
            cityInput.placeholder = 'Select a province first';
          }
        }
      });
    }
  };

})(Drupal, once);
