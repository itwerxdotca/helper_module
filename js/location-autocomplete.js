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
          cityInput.removeAttribute('disabled');
          cityInput.placeholder = 'Start typing city name...';
        } else {
          // No province selected - remove autocomplete path completely.
          cityInput.removeAttribute('data-autocomplete-path');
          cityInput.disabled = true;
          cityInput.setAttribute('disabled', 'disabled');
          cityInput.placeholder = 'Select a province first';
          cityInput.value = '';
        }
      });
    }
  };

  // Client-side province -> town cascade for Views exposed search forms
  // (listings_search, point_of_interest_search). Deliberately does NOT
  // rely on Form API's #ajax: Views exposed filter forms strip
  // form_id/form_build_id/form_token from exposed widgets, which breaks
  // the form-cache round trip #ajax depends on (core issue #2842525),
  // and reliably throws "The specified #ajax callback is empty or not
  // callable." Instead we toggle town directly and let Drupal's own
  // core/drupal.autocomplete behavior attach itself once
  // data-autocomplete-path appears - no reimplementation of autocomplete,
  // no jQuery of our own. Uses explicit element IDs set in
  // LocationFormHelper::alterSearchForm().
  Drupal.behaviors.searchTownProvinceCascade = {
    attach: function (context, settings) {
      const provinceSelects = once('town-province-cascade', '#search-town-province-select', context);

      provinceSelects.forEach(function (provinceSelect) {
        const form = provinceSelect.closest('form');
        if (!form) {
          return;
        }

        const townInput = form.querySelector('#search-town-field');
        if (!townInput) {
          return;
        }

        const paths = (settings.helperModule && settings.helperModule.townAutocompletePaths) || {};

        function stripTidSuffix(value) {
          var v = (value || '').trim();
          var close = v.lastIndexOf(')');
          var open = v.lastIndexOf('(');

          if (open === -1 || close !== v.length - 1 || open >= close) {
            return v;
          }

          var inside = v.substring(open + 1, close).trim();
          if (!inside) {
            return v;
          }

          for (var i = 0; i < inside.length; i++) {
            var code = inside.charCodeAt(i);
            if (code < 48 || code > 57) {
              return v;
            }
          }

          return v.substring(0, open).trim();
        }

        function applyState(clearValue) {
          const provinceId = provinceSelect.value;
          const path = provinceId ? paths[String(provinceId)] : null;

          if (path) {
            townInput.disabled = false;
            townInput.removeAttribute('disabled');
            townInput.placeholder = Drupal.t('Start typing town name...');
            townInput.setAttribute('data-autocomplete-path', path);
            townInput.classList.add('form-autocomplete');

            if (clearValue) {
              townInput.value = '';
            } else {
              // Cosmetic only: remove trailing " (123)" if present.
              townInput.value = stripTidSuffix(townInput.value);
            }
          } else {
            townInput.disabled = true;
            townInput.setAttribute('disabled', 'disabled');
            townInput.placeholder = Drupal.t('Select a province first');
            townInput.removeAttribute('data-autocomplete-path');
            townInput.classList.remove('form-autocomplete');

            if (clearValue) {
              townInput.value = '';
            } else {
              // Cosmetic only: remove trailing " (123)" if present.
              townInput.value = stripTidSuffix(townInput.value);
            }
          }
        }

        provinceSelect.addEventListener('change', function () {
          // Changing province invalidates whatever town was selected
          // under the old scope.
          applyState(true);
        });

        // Set correct initial state on load (covers both a fresh page
        // load and a rebuild where a province is already selected).
        applyState(false);
      });
    }
  };

})(Drupal, once);
