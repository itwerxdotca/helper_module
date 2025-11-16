<?php

declare(strict_types=1);

namespace Drupal\helper_module\Plugin\Helper;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides location handling for `field_canadian_towns`.
 *
 * Handles parent (`province`) dropdown and child (`city`) autocomplete dynamically.
 */
#[Helper(
  id: 'location_form',
  label: new TranslatableMarkup('Canadian Towns Location Helper'),
  description: new TranslatableMarkup('Manages provinces and cities dynamically for field_canadian_towns'),
  weight: 0,
)]
class LocationFormHelper extends HelperBase implements ContainerFactoryPluginInterface {

  /**
   * The entity type manager service.
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
   *   The entity type manager service.
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
   * Alters the form to provide a province dropdown and a city autocomplete.
   *
   * @param array $form
   *   The form structure to alter.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   */
  public function alterListingForm(array &$form, FormStateInterface $form_state): void {
    if (isset($form['field_canadian_towns'])) {
      // Add province dropdown to the form.
      $form['field_canadian_towns']['province'] = $this->buildProvinceDropdown();

      // Add city autocomplete to the form.
      $form['field_canadian_towns']['city'] = $this->buildCityAutocomplete();

      // Attach JavaScript for AJAX.
      $form['#attached']['library'][] = 'helper_module/location-autocomplete';
    }
  }

  /**
   * Builds the province dropdown field.
   *
   * @return array
   *   The province dropdown form element.
   */
  protected function buildProvinceDropdown(): array {
    // Load all the parent terms (provinces) from the vocabulary.
    $parent_terms = $this->entityTypeManager
      ->getStorage('taxonomy_term')
      ->loadTree('canadian_towns', 0, 1, FALSE);

    $options = [];
    foreach ($parent_terms as $term) {
      $options[$term->tid] = $term->name;
    }

    return [
      '#type' => 'select',
      '#title' => $this->t('Province'),
      '#options' => $options,
      '#empty_option' => $this->t('-- Select a Province --'),
      '#required' => TRUE,
      '#ajax' => [
        'callback' => '::updateCityAutocomplete',
        'wrapper' => 'field-canadian-towns-city-wrapper',
        'event' => 'change',
      ],
    ];
  }

  /**
   * Builds the city autocomplete field.
   *
   * @return array
   *   The city autocomplete form element.
   */
  protected function buildCityAutocomplete(): array {
    return [
      '#type' => 'textfield',
      '#title' => $this->t('City'),
      '#prefix' => '<div id="field-canadian-towns-city-wrapper">',
      '#suffix' => '</div>',
      '#disabled' => TRUE,
      '#placeholder' => $this->t('Select a province first'),
    ];
  }

  /**
   * AJAX callback to update the city autocomplete.
   *
   * @param array $form
   *   The altered form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The updated city field.
   */
  public function updateCityAutocomplete(array &$form, FormStateInterface $form_state): array {
    $province_id = $form_state->getValue(['field_canadian_towns', 'province'], 0);

    if ($province_id) {
      $form['field_canadian_towns']['city']['#disabled'] = FALSE;
      $form['field_canadian_towns']['city']['#placeholder'] = $this->t('Start typing city name...');
      $form['field_canadian_towns']['city']['#attributes']['data-autocomplete-path'] =
        "/helper-module/autocomplete/city/{$province_id}";
    }

    return $form['field_canadian_towns']['city'];
  }
}
