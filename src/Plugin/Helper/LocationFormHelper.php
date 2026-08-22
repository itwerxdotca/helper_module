<?php

declare(strict_types=1);

namespace Drupal\helper_module\Plugin\Helper;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\helper_module\Attribute\Helper;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Component\Utility\Crypt;
use Drupal\Core\Site\Settings;
use Drupal\Core\Url;

/**
 * Provides location handling for field_canadian_towns.
 *
 * Converts single hierarchical field into province dropdown + city autocomplete.
 */
#[Helper(
  id: 'location_form',
  label: new TranslatableMarkup('Canadian Towns Location Helper'),
  description: new TranslatableMarkup('Manages provinces and cities dynamically for field_canadian_towns'),
  weight: 0,
)]
class LocationFormHelper extends HelperBase implements ContainerFactoryPluginInterface {

  /**
   * The Entity Type Manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Constructs a LocationFormHelper object.
   */
  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
    EntityTypeManagerInterface $entity_type_manager
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager')
    );
  }

  /**
   * Alters the listing form.
   */
  public function alterListingForm(array &$form, FormStateInterface $form_state): void {
    if (!isset($form['field_canadian_towns'])) {
      return;
    }

    // Get current value if editing.
    $current_value = NULL;
    $current_parent = NULL;
    $entity = $form_state->getFormObject()->getEntity();

    if ($entity->id() && !$entity->get('field_canadian_towns')->isEmpty()) {
      $current_value = $entity->get('field_canadian_towns')->target_id;
      $term = $this->entityTypeManager->getStorage('taxonomy_term')->load($current_value);
      if ($term) {
        $parents = $this->entityTypeManager->getStorage('taxonomy_term')->loadParents($current_value);
        if (!empty($parents)) {
          $current_parent = reset($parents)->id();
        }
        else {
          // Selected term is already a parent (province).
          $current_parent = $current_value;
          $current_value = NULL;
        }
      }
    }

    // Read selected province from processed values first, then raw input.
    $selected_province = $form_state->getValue('field_canadian_towns_province');
    if ($selected_province === NULL || $selected_province === '') {
      $user_input = $form_state->getUserInput();
      $selected_province = $user_input['field_canadian_towns_province'] ?? NULL;
    }

    // Normalize empty selection markers.
    if ($selected_province === '_none' || $selected_province === '') {
      $selected_province = NULL;
    }

    // If user interacted with province in this request, trust it and clear stale city.
    if ($selected_province !== NULL) {
      $current_parent = $selected_province;
      $current_value = NULL;
    }
    else {
      // Explicitly handle "none selected" to keep city disabled/cleared.
      $raw_value = $form_state->getValue('field_canadian_towns_province');
      if ($raw_value === '_none') {
        $current_parent = NULL;
        $current_value = NULL;
      }
    }

    // Create inline container for location fields.
    $form['location_wrapper'] = [
      '#type' => 'container',
      '#weight' => $form['field_canadian_towns']['#weight'] ?? 0,
      '#attributes' => [
        'class' => ['location-fields-inline'],
        'style' => 'display: flex; gap: 1rem; flex-wrap: wrap;',
      ],
    ];

    $form['location_wrapper']['field_canadian_towns_province'] = [
      '#type' => 'select',
      '#title' => $this->t('Province'),
      '#options' => $this->getProvinceOptions(),
      '#empty_option' => $this->t('- Select Province -'),
      '#default_value' => $current_parent ?? '_none',
      '#required' => $form['field_canadian_towns']['widget']['#required'] ?? FALSE,
      '#attributes' => [
        'data-drupal-selector' => 'edit-field-canadian-towns-province',
        'style' => 'flex: 1; min-width: 200px;',
      ],
      '#ajax' => [
        'callback' => [$this, 'ajaxUpdateCityField'],
        'wrapper' => 'field-canadian-towns-city-wrapper',
        'event' => 'change',
      ],
    ];

    // Build the city field.
    $city_field = [
      '#type' => 'textfield',
      '#title' => $this->t('City'),
      '#default_value' => $current_value ? $this->getTermName((int) $current_value) . ' (' . $current_value . ')' : '',
      '#disabled' => empty($current_parent),
      '#placeholder' => empty($current_parent) ? $this->t('Select a province first') : $this->t('Start typing city name...'),
      '#attributes' => [
        'data-drupal-selector' => 'edit-field-canadian-towns-city',
        'data-province-id' => $current_parent ?? 0,
        'style' => 'flex: 1; min-width: 200px;',
      ],
      '#prefix' => '<div id="field-canadian-towns-city-wrapper" style="flex: 1; min-width: 200px;">',
      '#suffix' => '</div>',
    ];

    // Add autocomplete only when a valid province is selected.
    if (!empty($current_parent) && $current_parent !== '_none') {
      $city_field['#autocomplete_route_name'] = 'helper_module.city_autocomplete';
      $city_field['#autocomplete_route_parameters'] = ['province' => (int) $current_parent];
    }

    $form['location_wrapper']['field_canadian_towns_city'] = $city_field;

    // Move and style address field.
    if (isset($form['field_street_address'])) {
      $form['location_wrapper']['field_street_address'] = $form['field_street_address'];
      $form['location_wrapper']['field_street_address']['#weight'] = 10;

      // Hide the main label.
      $form['location_wrapper']['field_street_address']['#title_display'] = 'invisible';

      // Style the address widget wrapper.
      $form['location_wrapper']['field_street_address']['#attributes']['style'] = 'flex: 1 1 100%; min-width: 100%;';

      // Access the first (and only) delta item.
      if (isset($form['location_wrapper']['field_street_address']['widget'][0])) {
        $widget = &$form['location_wrapper']['field_street_address']['widget'][0];

        // Change fieldset to container to remove border.
        $widget['#type'] = 'container';
        $widget['#attributes']['style'] = 'border: none; padding: 0;';

        // Hide the title.
        $widget['#title_display'] = 'invisible';

        // Access the address element and apply inline styles.
        if (isset($widget['address'])) {
          $widget['address']['#attributes']['style'] = 'display: flex; gap: 1rem; flex-wrap: wrap;';

          // Style address_line1 (now visible based on field_overrides).
          if (isset($widget['address']['#address_element_properties']['address_line1'])) {
            $widget['address']['#address_element_properties']['address_line1']['#attributes']['style'] = 'flex: 2; min-width: 300px;';
            $widget['address']['#address_element_properties']['address_line1']['#attributes']['placeholder'] = $this->t('Street Address');
          }

          // Style postal_code (now visible based on field_overrides).
          if (isset($widget['address']['#address_element_properties']['postal_code'])) {
            $widget['address']['#address_element_properties']['postal_code']['#attributes']['style'] = 'flex: 1; min-width: 150px;';
            $widget['address']['#address_element_properties']['postal_code']['#attributes']['placeholder'] = $this->t('Postal Code');
          }
        }
      }

      unset($form['field_street_address']);
    }

    $form['field_canadian_towns']['#access'] = FALSE;
    $form['#validate'][] = [$this, 'validateCanadianTowns'];
    $form['#attached']['library'][] = 'helper_module/location-autocomplete';
  }

  /**
   * AJAX callback to update city field when province changes.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The city field element to replace.
   */
  public function ajaxUpdateCityField(array &$form, FormStateInterface $form_state): array {
    $form_state->setRebuild(TRUE);
    return $form['location_wrapper']['field_canadian_towns_city'];
  }

  /**
   * Gets province options (parent terms only).
   */
  protected function getProvinceOptions(): array {
    $parent_terms = $this->entityTypeManager
      ->getStorage('taxonomy_term')
      ->loadTree('canadian_towns', 0, 1, FALSE);

    $options = [];
    foreach ($parent_terms as $term) {
      $options[$term->tid] = $term->name;
    }

    return $options;
  }

  /**
   * Gets term name by ID.
   */
  protected function getTermName(int $tid): string {
    $term = $this->entityTypeManager->getStorage('taxonomy_term')->load($tid);
    return $term ? $term->getName() : '';
  }

  /**
   * Validation handler to map province/city back to field_canadian_towns.
   */
  public function validateCanadianTowns(array &$form, FormStateInterface $form_state): void {
    $city_value = $form_state->getValue('field_canadian_towns_city');

    if (!empty($city_value)) {
      // Extract term ID from "City Name (123)" format.
      if (preg_match('/\((\d+)\)$/', $city_value, $matches)) {
        $tid = (int) $matches[1];
        $form_state->setValue(['field_canadian_towns', 0, 'target_id'], $tid);
      }
    }
    else {
      // No city selected, use province.
      $province = $form_state->getValue('field_canadian_towns_province');
      if (!empty($province) && $province !== '_none') {
        $form_state->setValue(['field_canadian_towns', 0, 'target_id'], (int) $province);
      }
    }
  }

  /**
   * Alters a Views exposed search form to add province scoping to town.
   */
  public function alterSearchForm(array &$form, FormStateInterface $form_state): void {
    if (!isset($form['town']) || ($form['town']['#type'] ?? NULL) !== 'entity_autocomplete') {
      return;
    }

    $selected_province = $form_state->getValue('town_province');
    if ($selected_province === NULL || $selected_province === '') {
      $user_input = $form_state->getUserInput();
      $selected_province = $user_input['town_province'] ?? NULL;
    }

    $current_parent = NULL;
    if (!empty($selected_province) && $selected_province !== '_none') {
      $current_parent = $selected_province;
    }
    elseif (!empty($form['town']['#default_value'])) {
      $existing = $form['town']['#default_value'];
      $existing_term = is_array($existing) ? reset($existing) : $existing;
      if ($existing_term instanceof \Drupal\taxonomy\TermInterface) {
        $parents = $this->entityTypeManager->getStorage('taxonomy_term')->loadParents((int) $existing_term->id());
        if (!empty($parents)) {
          $current_parent = reset($parents)->id();
        }
      }
    }

    $form['town_province'] = [
      '#type' => 'select',
      '#title' => $this->t('Province'),
      '#options' => $this->getProvinceOptions(),
      '#empty_option' => $this->t('- Any Province -'),
      '#default_value' => $current_parent,
      '#weight' => $form['town']['#weight'] ?? -5,
      '#attributes' => [
        'id' => 'search-town-province-select',
      ],
      '#id' => 'edit-town-province',
    ];

    $form['town']['#title'] = $this->t('Town');
    $form['town']['#weight'] = ($form['town']['#weight'] ?? -5) + 1;
    $form['town']['#disabled'] = empty($current_parent);
    $form['town']['#id'] = 'edit-town';
    $form['town']['#attributes']['id'] = 'search-town-field';
    $form['town']['#attributes']['placeholder'] = empty($current_parent)
      ? $this->t('Select a province first')
      : $this->t('Start typing town name...');

    // Cosmetic: hide trailing "(123)" in visible value after reload.
    if (!empty($form['town']['#value']) && is_string($form['town']['#value'])) {
      $form['town']['#value'] = preg_replace('/\s*\(\d+\)\s*$/', '', $form['town']['#value']);
    }

    if (!empty($current_parent)) {
      $form['town']['#selection_handler'] = 'helper_module:province_scoped_term';
      $form['town']['#selection_settings']['parent_term'] = $current_parent;
    }

    $province_paths = $this->getProvinceAutocompleteSettings();
    $form['#attached']['drupalSettings']['helperModule']['townAutocompletePaths'] = $province_paths;
    $form['#attached']['library'][] = 'helper_module/location-autocomplete';
  }

  /**
   * Pre-computes a valid entity_autocomplete URL for every province.
   *
   * @return string[]
   *   An array of autocomplete URLs keyed by province term ID.
   */
  protected function getProvinceAutocompleteSettings(): array {
    $selection_handler = 'helper_module:province_scoped_term';
    $key_value_storage = \Drupal::keyValue('entity_autocomplete');
    $paths = [];

    foreach (array_keys($this->getProvinceOptions()) as $tid) {
      $selection_settings = [
        'target_bundles' => ['canadian_towns'],
        'parent_term' => $tid,
      ];

      $data = serialize($selection_settings) . 'taxonomy_term' . $selection_handler;
      $key = Crypt::hmacBase64($data, Settings::getHashSalt());

      if (!$key_value_storage->has($key)) {
        $key_value_storage->set($key, $selection_settings);
      }

      $paths[$tid] = Url::fromRoute('system.entity_autocomplete', [
        'target_type' => 'taxonomy_term',
        'selection_handler' => $selection_handler,
        'selection_settings_key' => $key,
      ])->toString();
    }

    return $paths;
  }

  /**
   * Geocodes and saves location data for a listing node.
   */
  public function geocodeListing(NodeInterface $node): void {
    if ($node->bundle() !== 'listing') {
      return;
    }

    $address_parts = [];

    // Add street address.
    if (!$node->get('field_street_address')->isEmpty()) {
      $address_field = $node->get('field_street_address')->first();
      if ($address_field) {
        if ($address_field->address_line1) {
          $address_parts[] = $address_field->address_line1;
        }
        if ($address_field->address_line2) {
          $address_parts[] = $address_field->address_line2;
        }
        if ($address_field->postal_code) {
          $address_parts[] = $address_field->postal_code;
        }
      }
    }

    // Add city/province from canadian_towns.
    if (!$node->get('field_canadian_towns')->isEmpty()) {
      $term = $node->get('field_canadian_towns')->entity;
      if ($term) {
        $address_parts[] = $term->getName();
        // Get parent (province) if this is a city.
        $parents = $this->entityTypeManager->getStorage('taxonomy_term')->loadParents($term->id());
        if (!empty($parents)) {
          $province = reset($parents);
          $address_parts[] = $province->getName();
        }
      }
    }

    $address_parts[] = 'Canada';

    $full_address = implode(', ', array_filter($address_parts));

    // Skip if no meaningful address data.
    if (empty($full_address) || $full_address === 'Canada') {
      return;
    }

    // Check if geolocation module is available.
    if (!\Drupal::hasService('plugin.manager.geolocation.geocoder')) {
      \Drupal::logger('helper_module')->error('Geolocation geocoder service not available. Make sure Geolocation Geocoder module is enabled.');
      return;
    }

    // Geocode using Geolocation module.
    try {
      $geocoder_manager = \Drupal::service('plugin.manager.geolocation.geocoder');
      $geocoder_definitions = $geocoder_manager->getDefinitions();

      if (empty($geocoder_definitions)) {
        \Drupal::logger('helper_module')->warning('No geocoder plugins available. Configure a geocoder in Geolocation settings.');
        return;
      }

      foreach ($geocoder_definitions as $plugin_id => $definition) {
        try {
          $geocoder = $geocoder_manager->createInstance($plugin_id);
          $result = $geocoder->geocode($full_address);

          if (!empty($result['location']['lat']) && !empty($result['location']['lng'])) {
            $node->set('field_street_location', [
              'lat' => $result['location']['lat'],
              'lng' => $result['location']['lng'],
            ]);
            return;
          }
        }
        catch (\Exception $e) {
          // Try next geocoder.
          continue;
        }
      }

      // If we get here, no geocoder succeeded.
      \Drupal::logger('helper_module')->warning('Failed to geocode address for listing @id: @address', [
        '@id' => $node->id(),
        '@address' => $full_address,
      ]);
    }
    catch (\Exception $e) {
      \Drupal::logger('helper_module')->error('Geocoding failed for listing @id: @message', [
        '@id' => $node->id(),
        '@message' => $e->getMessage(),
      ]);
    }
  }

}
