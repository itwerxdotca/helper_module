/**
 * @file
 * Province-scoped town autocomplete for exposed search forms.
 */
(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.searchTownAutocompleteScope = {
	attach: function (context, settings) {
	  var provinceSelects = once(
		'search-town-autocomplete-scope',
		'#search-town-province-select',
		context
	  );

	  provinceSelects.forEach(function (provinceSelect) {
		var form = provinceSelect.closest('form');
		if (!form) {
		  return;
		}

		var townField = form.querySelector('#search-town-field');
		if (!townField) {
		  return;
		}

		var paths = (settings.helperModule && settings.helperModule.townAutocompletePaths)
		  ? settings.helperModule.townAutocompletePaths
		  : {};

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
		  var provinceId = provinceSelect.value;

		  if (!provinceId || !paths[provinceId]) {
			townField.disabled = true;
			townField.setAttribute('disabled', 'disabled');
			townField.setAttribute('placeholder', Drupal.t('Select a province first'));
			townField.removeAttribute('data-autocomplete-path');
			if (clearValue) {
			  townField.value = '';
			} else {
			  townField.value = stripTidSuffix(townField.value);
			}
			return;
		  }

		  townField.disabled = false;
		  townField.removeAttribute('disabled');
		  townField.setAttribute('placeholder', Drupal.t('Start typing town name...'));
		  townField.setAttribute('data-autocomplete-path', paths[provinceId]);

		  if (clearValue) {
			townField.value = '';
		  } else {
			townField.value = stripTidSuffix(townField.value);
		  }
		}

		provinceSelect.addEventListener('change', function () {
		  applyState(true);
		});

		applyState(false);
	  });
	}
  };
})(Drupal, once);