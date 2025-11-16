<?php

declare(strict_types=1);

namespace Drupal\helper_module\Plugin\Helper;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\node\NodeInterface;
use Drupal\helper_module\Attribute\Helper;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides location handling for listing content type.
 *
 * Handles province dropdown, city autocomplete, and geocoding
 * for the listing content type.
 */
#[Helper(
  id: 'location_form',
  label: new TranslatableMarkup('Listing Location Helper'),
  description: new TranslatableMarkup('Manages province/city selection and geocoding for listings'),
  weight: 0,
)]
class LocationFormHelper extends HelperBase implements ContainerFactoryPluginInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Constructs a LocationFormHelper object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
    EntityTypeManagerInterface $entity_type_manager,
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
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function alterListingForm(array &$form, FormStateInterface $form_state): void {
    // Convert province to select dropdown (parent terms only).
    if (isset($form['field_province'])) {
      $this->setupProvinceField($form, $form_state);
    }

    // Set up city field with custom autocomplete.
    if (isset($form['field_city'])) {
      $this->setupCityField($form, $form_state);
    }

    // Attach JavaScript library.
    $form['#attached']['library'][] = 'helper_module/location-autocomplete';
  }

  /**
   * Sets up the province field as a dropdown.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
protected function setupProvinceField(array &$form, FormStateInterface $form_state): void {
     // DEBUG: Load ALL terms first to see structure
     $all_terms = $this->entityTypeManager
       ->getStorage('taxonomy_term')
       ->loadTree('canadian_locations', 0, NULL, FALSE);

     \Drupal::logger('helper_module')->notice('Total terms: @total', [
       '@total' => count($all_terms)
     ]);

     // Check first few terms
     foreach (array_slice($all_terms, 0, 5) as $term) {
       \Drupal::logger('helper_module')->notice('Term: @name (ID: @id, Parent: @parent, Depth: @depth)', [
         '@name' => $term->name,
         '@id' => $term->tid,
         '@parent' => implode(',', $term->parents),
         '@depth' => $term->depth,
       ]);
     }
   }  /**
   * Sets up the city field with dynamic autocomplete.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  protected function setupCityField(array &$form, FormStateInterface $form_state): void {
    // Get current province value if editing.
    $province_id = NULL;
    $entity = $form_state->getFormObject()->getEntity();
    if ($entity->id() && !$entity->get('field_province')->isEmpty()) {
      $province_id = $entity->get('field_province')->target_id;
    }

    if ($province_id) {
      // Set autocomplete path with province parameter.
      $form['field_city']['widget'][0]['target_id']['#autocomplete_route_name'] = 'helper_module.city_autocomplete';
      $form['field_city']['widget'][0]['target_id']['#autocomplete_route_parameters'] = ['province' => $province_id];
    }
    else {
      // Disable city field until province is selected.
      $form['field_city']['widget'][0]['target_id']['#disabled'] = TRUE;
      $form['field_city']['widget'][0]['target_id']['#placeholder'] = $this->t('Select a province first');
    }
  }

  /**
   * Geocodes and saves location data for a listing node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The listing node being saved.
   */
  public function geocodeListing(NodeInterface $node): void {
    // Only process listing nodes.
    if ($node->bundle() !== 'listing') {
      return;
    }

    // Build complete address string from fields.
    $address_parts = [];

    // Add street address if present.
    if (!$node->get('field_street_address')->isEmpty()) {
      $address_field = $node->get('field_street_address')->first();
      if ($address_field) {
        $address_parts[] = $address_field->get('address_line1')->getValue();
        if ($address_field->get('address_line2')->getValue()) {
          $address_parts[] = $address_field->get('address_line2')->getValue();
        }
      }
    }

    // Add city name.
    if (!$node->get('field_city')->isEmpty()) {
      $city = $node->get('field_city')->entity;
      if ($city) {
        $address_parts[] = $city->getName();
      }
    }

    // Add province name.
    if (!$node->get('field_province')->isEmpty()) {
      $province = $node->get('field_province')->entity;
      if ($province) {
        $address_parts[] = $province->getName();
      }
    }

    // Add country.
    $address_parts[] = 'Canada';

    // Build full address string.
    $full_address = implode(', ', array_filter($address_parts));

    // Skip if no address to geocode.
    if (empty($full_address)) {
      return;
    }

    // Geocode using Geolocation module.
    try {
      $geocoder_manager = \Drupal::service('plugin.manager.geolocation.geocoder');

      // Get the first available geocoder plugin (configured in Geolocation settings).
      $geocoder_definitions = $geocoder_manager->getDefinitions();

      if (empty($geocoder_definitions)) {
        \Drupal::logger('helper_module')->warning('No geocoder plugins available for listing @id', [
          '@id' => $node->id(),
        ]);
        return;
      }

      // Try each available geocoder.
      foreach ($geocoder_definitions as $plugin_id => $definition) {
        try {
          $geocoder = $geocoder_manager->createInstance($plugin_id);

          $result = $geocoder->geocode($full_address);

          if (!empty($result['location'])) {
            $latitude = $result['location']['lat'];
            $longitude = $result['location']['lng'];

            // Save to geolocation field.
            $node->set('field_geolocation', [
              'lat' => $latitude,
              'lng' => $longitude,
            ]);

            \Drupal::logger('helper_module')->info('Geocoded listing @id using @plugin: @address', [
              '@id' => $node->id(),
              '@plugin' => $plugin_id,
              '@address' => $full_address,
            ]);

            // Success - exit loop.
            return;
          }
        }
        catch (\Exception $e) {
          // Try next geocoder.
          continue;
        }
      }

      \Drupal::logger('helper_module')->warning('Could not geocode listing @id: @address', [
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
