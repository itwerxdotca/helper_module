<?php

declare(strict_types=1);

namespace Drupal\helper_module\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\helper_module\Plugin\Helper\HelperManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Province + town search that goes straight to the town's page.
 *
 * Province is a select of the top-level canadian_towns terms; Town is an
 * entity_autocomplete of the chosen province's child terms (the same
 * helper_module:province_scoped_term selection and per-province
 * autocomplete paths the listings/POI search forms use). Town stays
 * disabled until a province is picked - js/location-autocomplete.js does
 * that, matching the fields by their data-town-* attributes.
 *
 * Submitting redirects to the town term's own URL (its path alias, e.g.
 * /ontario/ottawa). Shown by the "Town search" block (TownSearchBlock).
 */
class TownSearchForm extends FormBase {

  public function __construct(
  protected HelperManager $helperManager,
  protected EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
  return new static(
    $container->get('plugin.manager.helper'),
    $container->get('entity_type.manager'),
  );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
  return 'helper_module_town_search_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
  /** @var \Drupal\helper_module\Plugin\Helper\LocationFormHelper $location */
  $location = $this->helperManager->createInstance('location_form');

  // Province chosen in this request (on submit), if any.
  $province = $form_state->getValue('province');
  if ($province === NULL || $province === '') {
    $province = $form_state->getUserInput()['province'] ?? NULL;
  }
  $province = ($province !== NULL && $province !== '' && ctype_digit((string) $province)) ? (int) $province : NULL;

  $form['#attributes']['class'][] = 'town-search-form';

  $form['province'] = [
    '#type' => 'select',
    '#title' => $this->t('Province'),
    '#options' => $location->getProvinceOptions(),
    '#empty_option' => $this->t('- Select Province -'),
    '#required' => TRUE,
    // Explicit IDs so this form never clashes with the search modal's
    // fields (which use edit-town-province / edit-town).
    '#id' => 'town-search-province',
    '#attributes' => [
    'data-town-province-select' => 'true',
    // Keep the phone's own select list. The Chosen module restyles every
    // select that is visible when the page loads; the search modal's
    // Province escapes that only because the modal is hidden then.
    // chosen-disable is Chosen's opt-out class.
    'class' => ['chosen-disable'],
    ],
  ];

  $selection_settings = ['target_bundles' => ['canadian_towns']];
  if ($province) {
    $selection_settings['parent_term'] = $province;
  }

  $form['town'] = [
    '#type' => 'entity_autocomplete',
    '#title' => $this->t('Name of Town'),
    '#target_type' => 'taxonomy_term',
    '#selection_handler' => 'helper_module:province_scoped_term',
    '#selection_settings' => $selection_settings,
    '#required' => TRUE,
    '#maxlength' => 128,
    '#id' => 'town-search-town',
    '#attributes' => [
    'data-town-autocomplete' => 'true',
    'placeholder' => $province
      ? $this->t('Start typing town name...')
      : $this->t('Select a province first'),
    ],
  ];

  // Off until a province is picked, from the first paint - not only once
  // the script runs. Only the HTML attribute: Form API's #disabled would
  // make Drupal ignore the submitted town. The script switches it on when
  // a province is chosen; on submit the province is known (above), so the
  // attribute isn't added.
  if (!$province) {
    $form['town']['#attributes']['disabled'] = 'disabled';
  }

  $form['actions'] = ['#type' => 'actions'];
  $form['actions']['submit'] = [
    '#type' => 'submit',
    '#value' => $this->t('Search'),
    '#button_type' => 'primary',
  ];

  $form['#attached']['drupalSettings']['helperModule']['townAutocompletePaths'] =
    $location->getProvinceAutocompleteSettings();
  $form['#attached']['library'][] = 'helper_module/location-autocomplete';

  return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
  // entity_autocomplete has already checked the town exists under the
  // chosen province (province_scoped_term selection); make sure it loads.
  $tid = $form_state->getValue('town');
  if ($tid && !$this->entityTypeManager->getStorage('taxonomy_term')->load($tid)) {
    $form_state->setErrorByName('town', $this->t('Choose a town from the list.'));
  }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
  $term = $this->entityTypeManager
    ->getStorage('taxonomy_term')
    ->load($form_state->getValue('town'));

  if ($term) {
    $form_state->setRedirectUrl($term->toUrl());
  }
  }

}
