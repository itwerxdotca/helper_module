/**
 * @file
 * Province-scoped town autocomplete for exposed search forms.
 *
 * Swaps the "town" entity_autocomplete endpoint when the paired province
 * select changes, using precomputed paths from:
 * drupalSettings.helperModule.townAutocompletePaths.
 */
(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.searchTownProvinceCascade = {
	attach: function (context, settings) {
	  const provinceSelects = once(
		'town-province-cascade',
		'#search-town-province-select',
		context
	  );

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

		// Remove trailing " (123)" without regex.
		function stripTidSuffix(value) {
		  var v = (value || '').trim();
		  if (!v) {
			return v;
		  }

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
			  townInput.value = stripTidSuffix(townInput.value);
			}
		  }
		}

		provinceSelect.addEventListener('change', function () {
		  // Changing province invalidates previously selected town.
		  applyState(true);
		});

		// Cosmetic: hide trailing "(123)" after user interaction.
		const inputs = once('strip-term-id-visual', '#search-town-field', context);
		inputs.forEach(function (input) {
		  input.addEventListener('blur', function () {
			input.value = stripTidSuffix(input.value);
		  });
		  input.addEventListener('change', function () {
			input.value = stripTidSuffix(input.value);
		  });
		});

		// Initial state on load/re-attach.
		applyState(false);
	  });
	}
  };

})(Drupal, once);