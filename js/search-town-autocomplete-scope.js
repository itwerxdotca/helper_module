/**
 * @file
 * Province-scoped town autocomplete for the listing/POI search forms.
 *
 * Swaps the "town" entity_autocomplete field's autocomplete endpoint when
 * the paired "town_province" select changes, using pre-computed per-province
 * URLs supplied via drupalSettings.helperModule.townAutocompletePaths (see
 * LocationFormHelper::getProvinceAutocompleteSettings()). No server round
 * trip on province change - this only swaps which existing, valid
 * autocomplete endpoint the field points at.
 */
(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.searchTownAutocompleteScope = {
	attach: function (context, settings) {
	  var provinceSelects = once(
		'search-town-autocomplete-scope',
		'[data-town-province-select="true"]',
		context
	  );

	  provinceSelects.forEach(function (provinceSelect) {
		var form = provinceSelect.closest('form');
		if (!form) {
		  return;
		}

		var townField = form.querySelector('[data-town-autocomplete="true"]');
		if (!townField) {
		  return;
		}

		var paths = (settings.helperModule && settings.helperModule.townAutocompletePaths) || {};

		var applyState = function () {
		  var provinceId = provinceSelect.value;

		  if (!provinceId || !paths[provinceId]) {
			townField.setAttribute('disabled', 'disabled');
			townField.setAttribute('placeholder', Drupal.t('Select a province first'));
			townField.removeAttribute('data-autocomplete-path');
			return;
		  }

		  townField.removeAttribute('disabled');
		  townField.setAttribute('placeholder', Drupal.t('Start typing town name...'));
		  townField.setAttribute('data-autocomplete-path', paths[provinceId]);
		};

		provinceSelect.addEventListener('change', function () {
		  // Changing province invalidates whatever town was selected
		  // under the old scope.
		  townField.value = '';
		  applyState();
		});

		// Set correct initial state on load (covers both a fresh page
		// load and a rebuild where a province is already selected).
		applyState();
	  });
	}
  };
})(Drupal, once);