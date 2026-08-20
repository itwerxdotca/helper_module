/**
 * @file
 * Province-scoped town autocomplete for the listing/POI search forms.
 *
 * Swaps the "town" entity_autocomplete field's autocomplete endpoint when
 * the paired "town_province" select changes, using pre-computed
 * per-province URLs from drupalSettings.helperModule.townAutocompletePaths
 * (see LocationFormHelper::getProvinceAutocompleteSettings()). No page
 * reload, no Form API #ajax (breaks on Views exposed forms - core issue
 * #2842525). Re-runs Drupal's own autocomplete attachment after enabling
 * the field, since a field with no data-autocomplete-path at page load
 * never gets that behavior attached in the first place.
 */
(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.searchTownProvinceScope = {
	attach: function (context, settings) {
	  once('search-town-province-scope', '#search-town-province-select', context).forEach(function (sel) {
		var townField = context.querySelector
		  ? context.querySelector('#search-town-field')
		  : document.getElementById('search-town-field');

		if (!townField) {
		  return;
		}

		var paths = (settings.helperModule && settings.helperModule.townAutocompletePaths) || {};

		sel.addEventListener('change', function () {
		  var provinceId = sel.value;
		  townField.value = '';

		  if (!provinceId || !paths[provinceId]) {
			townField.setAttribute('disabled', 'disabled');
			townField.setAttribute('placeholder', Drupal.t('Select a province first'));
			townField.removeAttribute('data-autocomplete-path');
			return;
		  }

		  townField.removeAttribute('disabled');
		  townField.setAttribute('placeholder', Drupal.t('Start typing town name...'));
		  townField.setAttribute('data-autocomplete-path', paths[provinceId]);

		  once.remove('autocomplete', townField);
		  Drupal.attachBehaviors(townField.closest('form'), settings);
		});
	  });
	}
  };
})(Drupal, once);