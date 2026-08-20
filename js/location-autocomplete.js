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

  // Client-side province -> town cascade for Views exposed search forms
  // (listings_search, point_of_interest_search). Deliberately does NOT
  // rely on Form API's #ajax: Views exposed filter forms strip
  // form_id/form_build_id/form_token from exposed widgets, which breaks
  // the form-cache round trip #ajax depends on (core issue #2842525),
  // and reliably throws "The specified #ajax callback is empty or not
  // callable." Instead we toggle town_city directly and let Drupal's own
  // core/drupal.autocomplete behavior attach itself once
  // data-autocomplete-path appears - no reimplementation of autocomplete,
  // no jQuery of our own. Uses the data-drupal-selector values Drupal
  // auto-generates from town_province/town_city's #parents (edit-town-
  // province, edit-town-city) - see LocationFormHelper::alterSearchForm().
  Drupal.behaviors.searchTownProvinceCascade = {
	attach: function (context, settings) {
	  const provinceSelects = once('town-province-cascade', '[data-drupal-selector="edit-town-province"]', context);

	  provinceSelects.forEach(function (provinceSelect) {
		const form = provinceSelect.closest('form');
		if (!form) {
		  return;
		}
		const cityInput = form.querySelector('[data-drupal-selector="edit-town-city"]');
		if (!cityInput) {
		  return;
		}
		const wrapper = cityInput.closest('#search-town-city-wrapper') || cityInput.parentElement;

		provinceSelect.addEventListener('change', function () {
		  const provinceId = provinceSelect.value;
		  cityInput.value = '';

		  if (provinceId && provinceId !== '0' && provinceId !== '_none') {
			cityInput.disabled = false;
			cityInput.placeholder = Drupal.t('Start typing town name...');
			cityInput.setAttribute('data-autocomplete-path', '/helper-module/autocomplete/city/' + encodeURIComponent(provinceId));
			// core/drupal.autocomplete's own behavior selects on this
			// class, not on data-autocomplete-path directly. Since
			// town_city was rendered without #autocomplete_route_name
			// (no province chosen yet at render time), Drupal never
			// added this class server-side, so core's attach selector
			// never matches it - even after attachBehaviors below.
			cityInput.classList.add('form-autocomplete');
		  }
		  else {
			cityInput.disabled = true;
			cityInput.placeholder = Drupal.t('Select a province first');
			cityInput.removeAttribute('data-autocomplete-path');
			cityInput.classList.remove('form-autocomplete');
		  }

		  // If the field wasn't already autocomplete-enabled on page
		  // load, core/drupal.autocomplete's own behavior never attached
		  // to it (it wasn't matched by the selector yet). Re-running
		  // attachBehaviors on just this wrapper lets core pick it up now
		  // that data-autocomplete-path is present; 'once' means this is
		  // a no-op if it's already attached.
		  Drupal.attachBehaviors(wrapper, settings);
		});
	  });
	}
  };

})(Drupal, once);