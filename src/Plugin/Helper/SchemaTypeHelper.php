<?php

declare(strict_types=1);

namespace Drupal\helper_module\Plugin\Helper;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\helper_module\Attribute\Helper;
use Drupal\taxonomy\TermInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides Schema. org type mapping for listing categories.
 *
 * Maps taxonomy terms to the most granular Schema.org types for structured data.
 */
#[Helper(
  id: 'schema_type_mapper',
  label: new TranslatableMarkup('Schema. org Type Mapper'),
  description: new TranslatableMarkup('Maps listing categories to Schema.org types dynamically'),
  weight: 10,
)]
class SchemaTypeHelper extends HelperBase implements ContainerFactoryPluginInterface {

  /**
   * Cache ID for Schema.org types list.
   */
  private const CACHE_ID = 'helper_module: schema_types';

  /**
   * Cache expiration (30 days).
   */
  private const CACHE_EXPIRE = 2592000;

  /**
   * Constructs a SchemaTypeHelper object.
   */
  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly CacheBackendInterface $cache,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('cache. default'),
    );
  }

  /**
   * Gets the Schema.org type for a taxonomy term.
   *
   * Uses intelligent matching in this order:
   * 1. Exact manual mapping (term ID)
   * 2. Dynamic name matching (term name to Schema.org type)
   * 3. Parent term matching (if term has parent)
   * 4. Default to LocalBusiness
   *
   * @param \Drupal\taxonomy\TermInterface $term
   *   The taxonomy term.
   *
   * @return string
   *   The Schema.org type.
   */
  public function getSchemaTypeForTerm(TermInterface $term): string {
    $term_id = (int) $term->id();
    $term_name = $term->getName();

    // 1. Check manual mappings first (for edge cases or overrides).
    $manual_mapping = $this->getManualMapping($term_id);
    if ($manual_mapping !== NULL) {
      return $manual_mapping;
    }

    // 2. Try dynamic name matching.
    $dynamic_match = $this->matchTermNameToSchemaType($term_name);
    if ($dynamic_match !== NULL) {
      return $dynamic_match;
    }

    // 3. Check parent term if this is a child category.
    $parents = $this->entityTypeManager->getStorage('taxonomy_term')->loadParents($term_id);
    if (!empty($parents)) {
      $parent = reset($parents);
      $parent_match = $this->matchTermNameToSchemaType($parent->getName());
      if ($parent_match !== NULL) {
        return $parent_match;
      }
    }

    // 4. Default fallback.
    return 'LocalBusiness';
  }

  /**
   * Matches a term name to a Schema. org type.
   *
   * @param string $term_name
   *   The taxonomy term name.
   *
   * @return string|null
   *   The matched Schema. org type, or NULL if no match found.
   */
  private function matchTermNameToSchemaType(string $term_name): ?string {
    $schema_types = $this->getAllSchemaTypes();
    $normalized_term = $this->normalizeString($term_name);

    // First:  Try exact match.
    foreach ($schema_types as $schema_type) {
      if ($this->normalizeString($schema_type) === $normalized_term) {
        return $schema_type;
      }
    }

    // Second: Try partial/contains match.
    foreach ($schema_types as $schema_type) {
      $normalized_schema = $this->normalizeString($schema_type);

      if (str_contains($normalized_term, $normalized_schema) ||
          str_contains($normalized_schema, $normalized_term)) {
        return $schema_type;
      }
    }

    // Third: Try similarity matching (80% threshold).
    $best_match = NULL;
    $best_similarity = 0;

    foreach ($schema_types as $schema_type) {
      similar_text(
        $normalized_term,
        $this->normalizeString($schema_type),
        $similarity
      );

      if ($similarity > 80 && $similarity > $best_similarity) {
        $best_similarity = $similarity;
        $best_match = $schema_type;
      }
    }

    return $best_match;
  }

  /**
   * Normalizes a string for comparison.
   *
   * @param string $string
   *   The string to normalize.
   *
   * @return string
   *   Normalized string (lowercase, no special chars, no spaces).
   */
  private function normalizeString(string $string): string {
    $normalized = strtolower($string);
    $normalized = preg_replace('/[^a-z0-9]/', '', $normalized);
    return $normalized;
  }

  /**
   * Gets all Schema. org LocalBusiness types.
   *
   * @return array
   *   Array of Schema.org type names.
   */
  private function getAllSchemaTypes(): array {
    // Check cache first.
    $cached = $this->cache->get(self::CACHE_ID);
    if ($cached !== FALSE) {
      return $cached->data;
    }

    // Build comprehensive list of Schema.org LocalBusiness types.
    $types = [
      'LocalBusiness', 'Organization', 'Place',
      'AnimalShelter', 'ArchiveOrganization',
      'AutomotiveBusiness', 'AutoBodyShop', 'AutoDealer', 'AutoPartsStore',
      'AutoRental', 'AutoRepair', 'AutoWash', 'GasStation',
      'MotorcycleDealer', 'MotorcycleRepair',
      'ChildCare', 'Dentist', 'DryCleaningOrLaundry',
      'EmergencyService', 'FireStation', 'Hospital', 'PoliceStation',
      'EmploymentAgency',
      'EntertainmentBusiness', 'AdultEntertainment', 'AmusementPark',
      'ArtGallery', 'Casino', 'ComedyClub', 'MovieTheater', 'NightClub',
      'FinancialService', 'AccountingService', 'AutomatedTeller',
      'BankOrCreditUnion', 'InsuranceAgency',
      'FoodEstablishment', 'Bakery', 'BarOrPub', 'Brewery',
      'CafeOrCoffeeShop', 'Distillery', 'FastFoodRestaurant',
      'IceCreamShop', 'Restaurant', 'Winery',
      'GovernmentOffice', 'PostOffice',
      'HealthAndBeautyBusiness', 'BeautySalon', 'DaySpa', 'HairSalon',
      'HealthClub', 'NailSalon', 'TattooParlor',
      'HomeAndConstructionBusiness', 'Electrician', 'GeneralContractor',
      'Carpenter', 'HVACBusiness', 'HousePainter', 'Locksmith',
      'MovingCompany', 'Plumber', 'RoofingContractor',
      'InternetCafe',
      'LegalService', 'Attorney', 'Notary',
      'Library',
      'LodgingBusiness', 'BedAndBreakfast', 'Campground', 'Hostel',
      'Hotel', 'Motel', 'Resort',
      'MedicalBusiness', 'CommunityHealth', 'MedicalClinic', 'Optician',
      'Pharmacy', 'Physician', 'Physiotherapy', 'Podiatrist',
      'PrimaryCare', 'Psychologist',
      'ProfessionalService', 'RadioStation', 'RealEstateAgent',
      'RecyclingCenter', 'SelfStorage', 'ShoppingCenter',
      'SportsActivityLocation', 'BowlingAlley', 'ExerciseGym',
      'GolfCourse', 'PublicSwimmingPool', 'SkiResort', 'SportsClub',
      'StadiumOrArena', 'TennisComplex',
      'Store', 'BikeStore', 'BookStore', 'ClothingStore', 'ComputerStore',
      'ConvenienceStore', 'DepartmentStore', 'ElectronicsStore', 'Florist',
      'FurnitureStore', 'GardenStore', 'GroceryStore', 'HardwareStore',
      'HobbyShop', 'HomeGoodsStore', 'JewelryStore', 'LiquorStore',
      'MensClothingStore', 'MobilePhoneStore', 'MovieRentalStore',
      'MusicStore', 'OfficeEquipmentStore', 'OutletStore', 'PawnShop',
      'PetStore', 'ShoeStore', 'SportingGoodsStore', 'TireShop',
      'ToyStore', 'WholesaleStore',
      'TelevisionStation', 'TouristInformationCenter', 'TravelAgency',
      'Museum', 'PerformingArtsTheater', 'MusicGroup', 'PestControl',
    ];

    $types = array_unique($types);
    sort($types);

    $this->cache->set(self:: CACHE_ID, $types, time() + self::CACHE_EXPIRE);

    return $types;
  }

  /**
   * Gets manual mapping for specific term IDs.
   *
   * @param int $term_id
   *   The taxonomy term ID.
   *
   * @return string|null
   *   The Schema.org type, or NULL if no manual mapping exists.
   */
  private function getManualMapping(int $term_id): ?string {
    $manual_mappings = [
      289 => 'ConvenienceStore',
      80 => 'Bakery',
      288 => 'LocalBusiness',
      256 => 'LocalBusiness',
      257 => 'LocalBusiness',
      268 => 'LocalBusiness',
      278 => 'ProfessionalService',
    ];

    return $manual_mappings[$term_id] ?? NULL;
  }

  /**
   * Clears the Schema.org types cache.
   */
  public function clearCache(): void {
    $this->cache->delete(self::CACHE_ID);
  }

}
