# SchemaTypeHelper Plugin

## Overview

The **SchemaTypeHelper** is a Helper Module plugin that automatically maps taxonomy terms from the `field_listing_category` field to the most appropriate Schema.org type for structured data output.  This ensures your business listings have accurate, granular Schema.org `@type` values in their JSON-LD markup, improving SEO and search engine visibility.

## Purpose

When displaying business listings on your Drupal site, each listing should have the most specific Schema.org type possible (e.g., `Restaurant` instead of generic `LocalBusiness`). This plugin:

- **Eliminates manual configuration** - No need to set Schema.org types individually per listing
- **Provides intelligent matching** - Automatically maps taxonomy term names to 200+ Schema.org types
- **Ensures SEO compliance** - Uses validated Schema.org LocalBusiness hierarchy
- **Supports growth** - New taxonomy terms are automatically matched without code changes
- **Improves search visibility** - More specific schema types lead to better rich snippets and local search results

## How It Works

### Integration with Metatag Module

This plugin works in conjunction with the **Metatag** and **Schema.org Metatag** modules.  When a listing node is viewed:

1. The Metatag module starts building JSON-LD structured data
2. `hook_metatag_alter()` is triggered (in `helper_module.module`)
3. The hook loads this SchemaTypeHelper plugin
4. The plugin examines the listing's `field_listing_category` taxonomy term
5. It intelligently matches the term to the most appropriate Schema. org type
6. The `schema_place_type` metatag is dynamically updated
7. The final JSON-LD output includes the correct `@type` for that specific business

### Matching Algorithm

The plugin uses a multi-tiered matching strategy to find the best Schema.org type:

#### 1. Manual Override (Highest Priority)
Check if a specific term ID has been manually mapped in the code.

**Example:**
```php
289 => 'ConvenienceStore',  // Term "Convience" (misspelled) manually mapped
```

#### 2. Exact Name Match
Normalize and compare term name to Schema.org type names.

**Examples:**
- `"Restaurant"` → `Restaurant` ✓
- `"Hotel"` → `Hotel` ✓
- `"Museum"` → `Museum` ✓

#### 3. Partial/Contains Match
Check if term name contains the Schema.org type name or vice versa.

**Examples:**
- `"Bars & Pubs"` → contains "bar" → `BarOrPub` ✓
- `"Hardware Store"` → contains "hardware" → `HardwareStore` ✓
- `"Bed & Breakfast"` → contains "bedandbreakfast" → `BedAndBreakfast` ✓

#### 4. Similarity Matching (80%+ threshold)
Use fuzzy string matching to find similar type names.

**Examples:**
- `"Plumbing"` → 85% similar to `Plumber` ✓
- `"Grocery"` → 90% similar to `GroceryStore` ✓

#### 5. Parent Term Fallback
If the term is a child in the taxonomy hierarchy, try matching the parent term.

**Example:**
```
Term: "Italian Restaurant" (no direct match)
  ↓
Parent: "Restaurants"
  ↓
Matches: Restaurant ✓
```

#### 6. Default Fallback (Lowest Priority)
If no match is found, default to `LocalBusiness`.

**Example:**
- `"Custom Business Type"` → `LocalBusiness`

## Supported Schema.org Types

The plugin includes all valid Schema.org LocalBusiness types and subtypes (200+ types), including:

### Food & Beverage
- `Restaurant`, `Bakery`, `BarOrPub`, `CafeOrCoffeeShop`, `FastFoodRestaurant`, `IceCreamShop`, `Brewery`, `Winery`, `Distillery`

### Lodging
- `Hotel`, `Motel`, `BedAndBreakfast`, `Hostel`, `Resort`, `Campground`

### Home & Construction
- `Electrician`, `Plumber`, `GeneralContractor`, `Carpenter`, `HVACBusiness`, `HousePainter`, `Locksmith`, `MovingCompany`, `RoofingContractor`

### Retail Stores
- `Store`, `ClothingStore`, `GroceryStore`, `HardwareStore`, `ConvenienceStore`, `PetStore`, `BookStore`, `FurnitureStore`, `JewelryStore`, `ToyStore`, and 20+ more

### Health & Beauty
- `HealthAndBeautyBusiness`, `BeautySalon`, `HairSalon`, `NailSalon`, `DaySpa`, `HealthClub`, `TattooParlor`

### Medical
- `MedicalClinic`, `Dentist`, `Optician`, `Pharmacy`, `Physiotherapy`, `Hospital`

### Professional Services
- `ProfessionalService`, `Attorney`, `AccountingService`, `RealEstateAgent`, `InsuranceAgency`

### Entertainment & Arts
- `ArtGallery`, `Museum`, `MovieTheater`, `NightClub`, `Casino`, `BowlingAlley`, `PerformingArtsTheater`

### Automotive
- `AutomotiveBusiness`, `AutoRepair`, `AutoDealer`, `AutoWash`, `GasStation`, `TireShop`

### Other
- `ChildCare`, `Library`, `PetStore`, `RecyclingCenter`, `SelfStorage`, `TravelAgency`, and many more

**See the full list** in the `getAllSchemaTypes()` method.

## Usage

### Basic Usage (Automatic)

1. **Install and enable** the helper_module
2. **Ensure Metatag and Schema.org Metatag modules are enabled**
3. **Configure Metatag** for the "listing" content type with a default `schema_place_type:  LocalBusiness`
4. **Add or edit a listing node** with a category selected in `field_listing_category`
5. **View the node** - the Schema.org type is automatically set based on the category
6. **Check the HTML source** - you'll see the correct `@type` in the JSON-LD output

### Adding New Taxonomy Terms

Simply add new terms to your `field_listing_category` vocabulary.  The plugin will automatically match them to appropriate Schema.org types.

**Examples of auto-matching:**
- Add term:  `"Coffee Shop"` → Auto-matches to `CafeOrCoffeeShop`
- Add term: `"Flower Shop"` → Auto-matches to `Florist`
- Add term: `"Tattoo Shop"` → Auto-matches to `TattooParlor`
- Add term: `"Dental Office"` → Auto-matches to `Dentist`

### Manual Overrides

If auto-matching doesn't work well for a specific term, add a manual override in the `getManualMapping()` method:

```php
private function getManualMapping(int $term_id): ?string {
  $manual_mappings = [
    // Existing overrides
    289 => 'ConvenienceStore',  // "Convience" (misspelled in taxonomy)
    80 => 'Bakery',             // "Cafè & Bakery" → prefer Bakery over CafeOrCoffeeShop

    // Add your custom overrides here
    1234 => 'SpecificSchemaType',  // Replace with actual term ID and type
  ];

  return $manual_mappings[$term_id] ?? NULL;
}
```

**To find a term ID:**
1. Go to **Structure → Taxonomy → Listing Category** (`admin/structure/taxonomy/manage/listing_category/overview`)
2. Click **Edit** on the term
3. Look at the URL:  `/taxonomy/term/123/edit` - the number is the term ID

## Debugging & Verification

### Check Watchdog Logs

Enable debug logging to see what Schema.org types are being assigned:

```bash
drush watchdog-show --filter=helper_module
```

You'll see entries like:
```
Updated schema_place_type to Restaurant for listing 456 (term:  Restaurants [tid: 20])
Updated schema_place_type to Hotel for listing 789 (term: Hotel [tid: 283])
```

### Validate JSON-LD Output

1. **View a listing node**
2. **View page source** (Ctrl+U or Cmd+U)
3. **Search for `application/ld+json`**
4. **Verify the `@type` field** matches your expectation:

```json
{
  "@context": "https://schema.org",
  "@graph": [
    {
      "@type": "Restaurant",
      "name": "Joe's Pizza",
      "address": { ...  },
      ...
    }
  ]
}
```

### Use Schema.org Validators

Copy the JSON-LD output and validate it:
- [Google Rich Results Test](https://search.google.com/test/rich-results)
- [Schema.org Validator](https://validator.schema.org/)
- [Structured Data Testing Tool](https://developers.google.com/search/docs/appearance/structured-data)

## Performance

### Caching

The list of 200+ Schema.org types is cached for **30 days** to improve performance. The cache is automatically refreshed when it expires.

**To manually clear the cache:**
```bash
drush cache-rebuild
```

Or programmatically:
```php
$schema_helper = \Drupal::service('plugin. manager.helper')->createInstance('schema_type_mapper');
$schema_helper->clearCache();
```

### Load Impact

The plugin is optimized for minimal performance impact:
- Schema type list is loaded once and cached
- Only runs on listing nodes (not all pages)
- Uses efficient string matching algorithms
- Dependency injection ensures services are reused

## Requirements

- **Drupal**:  11.x
- **PHP**: 8.1+
- **Modules**:
  - helper_module (this module)
  - metatag
  - schema_metatag (Schema.org Metatag)
  - taxonomy
- **Content Type**: `listing` with field `field_listing_category` (taxonomy reference)

## Configuration

### Metatag Configuration

Ensure your **listing** content type has Schema.org Place metatags configured:

1. Go to **Configuration → Search and metadata → Metatag** (`/admin/config/search/metatag`)
2. Click **Content:  listing** (or add it if it doesn't exist)
3. Under **Schema.org** section, configure:
   - `schema_place_type`: `LocalBusiness` (default, will be overridden dynamically)
   - `schema_place_name`: `[current-page: title]`
   - `schema_place_address`: (configure with address tokens)
   - `schema_place_geo`: (configure with lat/long tokens)
   - Other Place properties as needed

The `schema_place_type` will be dynamically overridden by this plugin based on the category.

## Extending the Plugin

### Adding New Schema.org Types

If Schema.org releases new types, add them to the `getAllSchemaTypes()` method:

```php
private function getAllSchemaTypes(): array {
  // Check cache first...

  $types = [
    // Existing types...

    // Add new types here
    'NewSchemaType',
    'AnotherNewType',
  ];

  // Rest of method...
}
```

Then clear cache:
```bash
drush cache-rebuild
```

### Adjusting Match Sensitivity

To change the similarity threshold (default 80%), edit the condition in `matchTermNameToSchemaType()`:

```php
// Change from 80 to 70 for looser matching, or 90 for stricter matching
if ($similarity > 80 && $similarity > $best_similarity) {
  $best_similarity = $similarity;
  $best_match = $schema_type;
}
```

### Supporting Multiple Content Types

To use this plugin for other content types beyond "listing", modify the condition in `hook_metatag_alter()`:

```php
// Change this line in helper_module.module
if ($entity->getEntityTypeId() !== 'node' || $entity->bundle() !== 'listing') {
  return;
}

// To support multiple content types:
$supported_bundles = ['listing', 'business', 'venue'];
if ($entity->getEntityTypeId() !== 'node' || !in_array($entity->bundle(), $supported_bundles)) {
  return;
}
```

## Troubleshooting

### Schema type not changing

**Problem**: The Schema.org type is still showing as `LocalBusiness` even though you have a specific category selected.

**Solutions**:
1. Clear Drupal cache:  `drush cache-rebuild`
2. Check that `field_listing_category` field exists and has a value
3. Check watchdog logs for errors:  `drush watchdog-show --filter=helper_module`
4. Verify the Metatag module has `schema_place_type` configured

### Wrong Schema type assigned

**Problem**: The auto-matching is selecting the wrong Schema.org type for a specific term.

**Solutions**:
1. Add a manual override in `getManualMapping()` for that specific term ID
2. Rename the taxonomy term to better match the desired Schema.org type
3. Check parent term - the plugin may be using parent fallback

### Plugin not loading

**Problem**: Getting errors about the plugin not being found.

**Solutions**:
1. Verify file is at correct path: `src/Plugin/Helper/SchemaTypeHelper.php`
2. Verify namespace is correct: `namespace Drupal\helper_module\Plugin\Helper;`
3. Clear cache: `drush cache-rebuild`
4. Check that the `#[Helper]` attribute is present and correct

## SEO Benefits

Using this plugin provides significant SEO advantages:

### Rich Snippets
More specific Schema.org types enable enhanced search results:
- ⭐ Star ratings for restaurants
- 📍 Map locations for local businesses
- 🕐 Opening hours display
- 📞 Click-to-call phone numbers
- 💰 Price range indicators

### Local Search
Accurate business types improve local search rankings:
- Better matching for "near me" searches
- More relevant in Google Maps results
- Improved Business Profile integration

### Voice Search
Structured data helps voice assistants understand your business:
- "Find a restaurant near me" → Your restaurant listings appear
- "Book a hotel in [city]" → Your hotel listings are considered

## Further Reading

- [Schema.org LocalBusiness Documentation](https://schema.org/LocalBusiness)
- [Google Search Central:  Local Business Structured Data](https://developers.google.com/search/docs/appearance/structured-data/local-business)
- [Drupal Schema.org Metatag Module Documentation](https://www.drupal.org/docs/contributed-modules/schemaorg-metatag)
- [Helper Module Plugin Architecture](../README.md)

## License

This plugin follows the same license as the helper_module.

## Support

For issues, questions, or contributions related to this plugin, please refer to the helper_module repository.

---

**Version**: 1.0
**Last Updated**: 2026-01-13
**Maintained by**: helper_module team
