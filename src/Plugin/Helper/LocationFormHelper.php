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
          $current_parent = $current_value;
          $current_value = NULL;
        }
      }
    }

    // Check if province was changed via AJAX - if so, clear city value.
    $selected_province = $form_state->getValue('field_canadian_towns_province');
    if ($selected_province !== NULL) {
      // Province changed via AJAX, use the new value and clear city.
      $current_parent = $selected_province;
      $current_value = NULL;
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
      '#default_value' => $current_parent,
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

    $form['location_wrapper']['field_canadian_towns_city'] = [
      '#type' => 'textfield',
      '#title' => $this->t('City'),
      '#default_value' => $current_value ? $this->getTermName($current_value) . ' (' . $current_value . ')' : '',
      '#disabled' => empty($current_parent),
      '#placeholder' => empty($current_parent) ? $this->t('Select a province first') : $this->t('Start typing city name...'),
      '#autocomplete_route_name' => 'helper_module.city_autocomplete',
      '#autocomplete_route_parameters' => ['province' => $current_parent ?? 0],
      '#attributes' => [
        'data-drupal-selector' => 'edit-field-canadian-towns-city',
        'data-province-id' => $current_parent ?? 0,
        'style' => 'flex: 1; min-width: 200px;',
      ],
      '#prefix' => '<div id="field-canadian-towns-city-wrapper" style="flex: 1; min-width: 200px;">',
      '#suffix' => '</div>',
    ];

    // Move address field into the wrapper and make it full width.
    if (isset($form['field_listing_address'])) {
      $form['location_wrapper']['field_listing_address'] = $form['field_listing_address'];
      $form['location_wrapper']['field_listing_address']['#weight'] = 10;
      $form['location_wrapper']['field_listing_address']['#attributes']['style'] = 'flex: 1 1 100%; min-width: 100%;';
      unset($form['field_listing_address']);
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
        $tid = $matches[1];
        $form_state->setValue(['field_canadian_towns', 0, 'target_id'], $tid);
      }
    }
    else {
      // No city selected, use province.
      $province = $form_state->getValue('field_canadian_towns_province');
      if (!empty($province) && $province !== '_none') {
        $form_state->setValue(['field_canadian_towns', 0, 'target_id'], $province);
      }
    }
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
    if (!$node->get('field_listing_address')->isEmpty()) {
      $address_field = $node->get('field_listing_address')->first();
      if ($address_field) {
        if ($address_field->address_line1) {
          $address_parts[] = $address_field->address_line1;
        }
        if ($address_field->address_line2) {
          $address_parts[] = $address_field->address_line2;
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

    if (empty($full_address)) {
      return;
    }

    // Geocode using Geolocation module.
    try {
      $geocoder_manager = \Drupal::service('plugin.manager.geolocation.geocoder');
      $geocoder_definitions = $geocoder_manager->getDefinitions();

      if (empty($geocoder_definitions)) {
        return;
      }

      foreach ($geocoder_definitions as $plugin_id => $definition) {
        try {
          $geocoder = $geocoder_manager->createInstance($plugin_id);
          $result = $geocoder->geocode($full_address);

          if (!empty($result['location'])) {
            $node->set('field_street_location', [
              'lat' => $result['location']['lat'],
              'lng' => $result['location']['lng'],
            ]);
            return;
          }
        }
        catch (\Exception $e) {
          continue;
        }
      }
    }
    catch (\Exception $e) {
      \Drupal::logger('helper_module')->error('Geocoding failed: @message', [
        '@message' => $e->getMessage(),
      ]);
    }
  }

}
