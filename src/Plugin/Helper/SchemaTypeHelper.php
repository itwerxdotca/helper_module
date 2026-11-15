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
 * Provides Schema.org type mapping for listing and point of interest categories.
 *
 * Maps taxonomy terms to the most granular Schema.org types for structured
 * data. Every type used here exists in Schema.org (checked against release
 * 30.0), is not deprecated, and is a kind of Place, so the address and
 * coordinates output with it stay valid.
 */
#[Helper(
  id: 'schema_type_mapper',
  label: new TranslatableMarkup('Schema.org Type Mapper'),
  description: new TranslatableMarkup('Maps listing and point of interest categories to Schema.org types'),
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
      $container->get('cache.default'),
    );
  }

  /**
   * Gets the Schema.org type for a category term.
   *
   * In this order:
   * 1. The manual mapping for the term, or else for its nearest parent that
   *    has one (a new subcategory takes its parent's type).
   * 2. Dynamic name matching (term name to Schema.org type).
   * 3. Dynamic name matching on the parent term's name.
   * 4. The default.
   *
   * Every existing category is covered by step 1; steps 2 and 3 only apply
   * to a new top-level category that is not in the mapping yet.
   *
   * @param \Drupal\taxonomy\TermInterface $term
   *   The taxonomy term (listing_category or poi_category).
   * @param string $default
   *   The type when nothing matches: 'LocalBusiness' for listings,
   *   'TouristAttraction' for points of interest.
   *
   * @return string
   *   The Schema.org type.
   */
  public function getSchemaTypeForTerm(TermInterface $term, string $default = 'LocalBusiness'): string {
    $term_id = (int) $term->id();
    $storage = $this->entityTypeManager->getStorage('taxonomy_term');

    // 1. Manual mapping: the term first, then each parent up the tree.
    foreach ($storage->loadAllParents($term_id) as $ancestor) {
      $manual_mapping = $this->getManualMapping((int) $ancestor->id());
      if ($manual_mapping !== NULL) {
        return $manual_mapping;
      }
    }

    // 2. Try dynamic name matching.
    $dynamic_match = $this->matchTermNameToSchemaType($term->getName());
    if ($dynamic_match !== NULL) {
      return $dynamic_match;
    }

    // 3. Check parent term if this is a child category.
    $parents = $storage->loadParents($term_id);
    if (!empty($parents)) {
      $parent = reset($parents);
      $parent_match = $this->matchTermNameToSchemaType($parent->getName());
      if ($parent_match !== NULL) {
        return $parent_match;
      }
    }

    // 4. Default fallback.
    return $default;
  }

  /**
   * Matches a term name to a Schema.org type.
   *
   * @param string $term_name
   *   The taxonomy term name.
   *
   * @return string|null
   *   The matched Schema.org type, or NULL if no match found.
   */
  private function matchTermNameToSchemaType(string $term_name): ?string {
    $schema_types = $this->getAllSchemaTypes();
    $normalized_term = $this->normalizeString($term_name);

    // First: Try exact match.
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
   * Gets the Schema.org types used for name matching.
   *
   * LocalBusiness and its subtypes, plus Place. Types that do not exist in
   * Schema.org, are not a kind of Place, or are deprecated are left out.
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

    $types = [
      'AccountingService', 'AdultEntertainment', 'AmusementPark', 'AnimalShelter',
      'ArtGallery', 'Attorney', 'AutoBodyShop', 'AutoDealer', 'AutoPartsStore',
      'AutoRental', 'AutoRepair', 'AutoWash', 'AutomatedTeller', 'AutomotiveBusiness',
      'Bakery', 'BankOrCreditUnion', 'BarOrPub', 'BeautySalon', 'BedAndBreakfast',
      'BikeStore', 'BookStore', 'BowlingAlley', 'Brewery', 'CafeOrCoffeeShop',
      'Campground', 'Casino', 'ChildCare', 'ClothingStore', 'ComedyClub',
      'CommunityHealth', 'ComputerStore', 'ConvenienceStore', 'DaySpa',
      'Dentist', 'DepartmentStore', 'Distillery', 'DryCleaningOrLaundry',
      'Electrician', 'ElectronicsStore', 'EmergencyService', 'EmploymentAgency',
      'EntertainmentBusiness', 'ExerciseGym', 'FastFoodRestaurant', 'FinancialService',
      'FireStation', 'Florist', 'FoodEstablishment', 'FurnitureStore',
      'GardenStore', 'GasStation', 'GeneralContractor', 'GolfCourse',
      'GovernmentOffice', 'GroceryStore', 'HVACBusiness', 'HairSalon',
      'HardwareStore', 'HealthAndBeautyBusiness', 'HealthClub', 'HobbyShop',
      'HomeAndConstructionBusiness', 'HomeGoodsStore', 'Hospital', 'Hostel',
      'Hotel', 'HousePainter', 'IceCreamShop', 'InsuranceAgency', 'InternetCafe',
      'JewelryStore', 'LegalService', 'Library', 'LiquorStore', 'LocalBusiness',
      'Locksmith', 'LodgingBusiness', 'MedicalBusiness', 'MedicalClinic',
      'MensClothingStore', 'MobilePhoneStore', 'Motel', 'MotorcycleDealer',
      'MotorcycleRepair', 'MovieRentalStore', 'MovieTheater', 'MovingCompany',
      'Museum', 'MusicStore', 'NailSalon', 'NightClub', 'Notary', 'OfficeEquipmentStore',
      'Optician', 'OutletStore', 'PawnShop', 'PerformingArtsTheater',
      'PetStore', 'Pharmacy', 'Physician', 'Physiotherapy', 'Place',
      'Plumber', 'PoliceStation', 'PostOffice', 'PrimaryCare', 'PublicSwimmingPool',
      'RadioStation', 'RealEstateAgent', 'RecyclingCenter', 'Resort',
      'Restaurant', 'RoofingContractor', 'SelfStorage', 'ShoeStore',
      'ShoppingCenter', 'SkiResort', 'SportingGoodsStore', 'SportsActivityLocation',
      'SportsClub', 'StadiumOrArena', 'Store', 'TattooParlor', 'TelevisionStation',
      'TennisComplex', 'TireShop', 'TouristInformationCenter', 'ToyStore',
      'TravelAgency', 'WholesaleStore', 'Winery',
    ];

    $types = array_unique($types);
    sort($types);

    $this->cache->set(self::CACHE_ID, $types, time() + self::CACHE_EXPIRE);

    return $types;
  }

  /**
   * Gets the manual mapping for a category term ID.
   *
   * A type set on a parent category also applies to its subcategories
   * unless they have their own (see getSchemaTypeForTerm()).
   *
   * @param int $term_id
   *   The taxonomy term ID.
   *
   * @return string|null
   *   The Schema.org type, or NULL if no manual mapping exists.
   */
  private function getManualMapping(int $term_id): ?string {
    $manual_mappings = [
      // Listing categories (listing_category).
      166877 => 'LocalBusiness', // Organizations & Nonprofits
      166879 => 'LocalBusiness', // Outdoor & Adventure
      16 => 'LodgingBusiness', // Accommodations
      282 => 'BedAndBreakfast', // Bed & Breakfast
      166934 => 'Campground', // Campground & RV Park
      283 => 'Hotel', // Hotel
      166932 => 'Motel', // Motel
      166703 => 'Resort', // Resort & Lodge
      284 => 'Hostel', // Travellers Hostel
      166936 => 'VacationRental', // Vacation Rentals
      255 => 'FoodEstablishment', // Food & Beverage
      19 => 'BarOrPub', // Bars & Pubs
      166943 => 'Brewery', // Breweries
      166941 => 'Restaurant', // Deli & Sandwich Shops
      166945 => 'Distillery', // Distilleries
      166939 => 'FastFoodRestaurant', // Fast Food
      166942 => 'IceCreamShop', // Ice Cream & Desserts
      166944 => 'Winery', // Wineries
      80 => 'CafeOrCoffeeShop', // Cafe & Bakery
      20 => 'Restaurant', // Restaurants
      256 => 'LocalBusiness', // Manufacturing & Production
      18 => 'Store', // Retail
      166958 => 'AutoPartsStore', // Automotive Parts & Accessories
      166967 => 'HardwareStore', // Building Supplies
      289 => 'ConvenienceStore', // Convenience
      166960 => 'ElectronicsStore', // Electronics
      259 => 'ClothingStore', // Fashion
      166969 => 'SportingGoodsStore', // Fishing & Hunting Supplies
      258 => 'GroceryStore', // Grocery
      286 => 'HardwareStore', // Hardware
      166961 => 'HomeGoodsStore', // Home & Furniture
      166963 => 'JewelryStore', // Jewellery
      166964 => 'LiquorStore', // Liquor
      166957 => 'SportingGoodsStore', // Outdoor & Camping
      166956 => 'SportingGoodsStore', // Sporting Goods
      253 => 'LocalBusiness', // Services
      287 => 'AutomotiveBusiness', // Auto
      166882 => 'AutoRepair', // Auto Repair & Mechanics
      166973 => 'AutoPartsStore', // Automotive Parts
      166885 => 'AutoDealer', // Car Dealerships
      166886 => 'AutoRental', // Car Rental
      166883 => 'AutoWash', // Car Wash
      166881 => 'GasStation', // Gas Stations
      166975 => 'AutoDealer', // RV & Trailer Dealers
      166974 => 'AutoRepair', // RV & Trailer Services
      166972 => 'TireShop', // Tire Services
      166978 => 'AutoWash', // Vehicle Detailing
      260 => 'HomeAndConstructionBusiness', // Contractor
      261 => 'Electrician', // Electrician
      262 => 'GeneralContractor', // General Contractor
      166981 => 'HVACBusiness', // HVAC & Heating
      166984 => 'HousePainter', // Painting
      266 => 'Plumber', // Plumber
      166980 => 'RoofingContractor', // Roofing
      274 => 'EntertainmentBusiness', // Event & Entertainment
      166995 => 'LocalBusiness', // Event Rentals
      275 => 'LocalBusiness', // Photographers
      277 => 'LocalBusiness', // Planners
      166992 => 'EventVenue', // Venues
      166999 => 'LocalBusiness', // Wedding Services
      166873 => 'FinancialService', // Financial
      167002 => 'AutomatedTeller', // ATMs
      167000 => 'BankOrCreditUnion', // Banks
      167001 => 'BankOrCreditUnion', // Credit Unions
      167003 => 'InsuranceAgency', // Insurance
      167007 => 'AccountingService', // Tax Services
      271 => 'LocalBusiness', // Health & Wellness
      166880 => 'HealthAndBeautyBusiness', // Beauty & Spa Services
      167011 => 'MedicalBusiness', // Chiropractor
      167009 => 'Dentist', // Dentist
      272 => 'ExerciseGym', // Fitness
      167015 => 'MedicalBusiness', // Hearing Services
      167013 => 'MedicalBusiness', // Massage Therapy
      166874 => 'MedicalBusiness', // Medical
      273 => 'MedicalBusiness', // Mental Health
      167014 => 'MedicalBusiness', // Naturopath
      167010 => 'MedicalBusiness', // Optometrist
      167008 => 'Pharmacy', // Pharmacy
      167012 => 'Physiotherapy', // Physiotherapy
      263 => 'HomeAndConstructionBusiness', // Home & Property
      166891 => 'DryCleaningOrLaundry', // Laundry & Dry Cleaning
      166892 => 'Locksmith', // Locksmith
      167022 => 'MovingCompany', // Moving Services
      167018 => 'RealEstateAgent', // Real Estate
      167021 => 'SelfStorage', // Storage
      166876 => 'LocalBusiness', // Office & Professional Services
      167026 => 'AccountingService', // Accounting & Bookkeeping
      167027 => 'LegalService', // Legal Services
      167033 => 'Notary', // Notary Services
      167035 => 'EmploymentAgency', // Recruitment & Employment
      278 => 'LocalBusiness', // Personal
      279 => 'ChildCare', // Childcare
      167036 => 'HairSalon', // Hair Salons & Barbers
      167037 => 'HealthAndBeautyBusiness', // Massage
      166890 => 'PostOffice', // Post Office
      166878 => 'LocalBusiness', // Tours & Travel Services
      167040 => 'TravelAgency', // Tour Operators
      167041 => 'TravelAgency', // Travel Agencies
      167049 => 'TouristInformationCenter', // Travel Information
      257 => 'WholesaleStore', // Wholesale & Distribution
      58 => 'LocalBusiness', // Arts & Culture
      167056 => 'Store', // Craft & Artisan Shops
      290 => 'ArtGallery', // Gallery
      // Point of interest categories (poi_category).
      167108 => 'TouristAttraction', // Arts & Attractions
      167110 => 'AmusementPark', // Amusement Parks
      167109 => 'Aquarium', // Aquariums
      167115 => 'ArtGallery', // Galleries
      167119 => 'EventVenue', // Performance Spaces
      167121 => 'PerformingArtsTheater', // Theatres
      167122 => 'Zoo', // Zoos & Wildlife Attractions
      167136 => 'CivicStructure', // Community & Public Services
      167137 => 'FireStation', // Fire Stations
      167140 => 'GovernmentOffice', // Government Offices
      167139 => 'Hospital', // Hospitals
      167138 => 'PoliceStation', // Police Stations
      167141 => 'TouristInformationCenter', // Visitor Information Centres
      95 => 'LandmarksOrHistoricalBuildings', // History & Heritage
      167073 => 'Cemetery', // Cemeteries & Burial Grounds
      167071 => 'Museum', // Museums
      167129 => 'TouristAttraction', // Landmarks & Structures
      167130 => 'Bridge', // Bridges
      167133 => 'Canal', // Canals & Locks
      89 => 'TouristAttraction', // Nature & Landscapes
      167088 => 'Beach', // Beaches
      167093 => 'Landform', // Caves
      167094 => 'Landform', // Geologic Features
      167089 => 'LakeBodyOfWater', // Lakes
      167090 => 'Mountain', // Mountains
      167092 => 'RiverBodyOfWater', // Rivers & Waterways
      167086 => 'Waterfall', // Waterfalls
      103 => 'Park', // Parks
      167084 => 'Playground', // Playgrounds
      123 => 'PlaceOfWorship', // Religious & Sacred Places
      167098 => 'Church', // Churches
      167100 => 'Mosque', // Mosques
      167103 => 'TouristAttraction', // Sacred Sites
      167101 => 'Synagogue', // Synagogues
      167123 => 'TouristAttraction', // Trails & Routes
      94 => 'TouristAttraction', // Unusual & Quirky
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
