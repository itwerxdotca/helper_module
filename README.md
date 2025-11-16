# Helper Module

A pluggable architecture for site-specific customizations using the Drupal 11+ plugin system.

## Project Intent

Helper Module provides a clean, performant framework for organizing site-specific customizations without creating module proliferation. Instead of creating dozens of small modules for individual features, Helper Module uses Drupal's plugin system to organize helpers as discoverable, testable, and maintainable plugins within a single module.

### Why Helper Module?

- **Performance**: Single module bootstrap instead of loading multiple small modules
- **Organization**: Plugins keep code organized and discoverable
- **Maintainability**: Each helper is a self-contained class with clear responsibilities
- **Scalability**: Add new helpers without cluttering .module files
- **Testability**: Plugins are classes that can be unit tested
- **Drupal 11+ Native**: Uses modern PHP 8.1+ attributes, strict typing, and Drupal 11 best practices

## Requirements

- Drupal: ^11
- PHP: ^8.1

## Installation

1. Download or clone this module into your `modules/custom` directory
2. Enable the module: `drush en helper-module`

## Creating Helper Plugins

Helper plugins are PHP classes that extend `HelperBase` and use the `#[Helper]` attribute.

### Basic Example

Create a file: `modules/custom/your-module/src/Plugin/Helper/ExampleHelper.php`

```php
<?php

declare(strict_types=1);

namespace Drupal\your_module\Plugin\Helper;

use Drupal\helper_module\Plugin\Helper\HelperBase;
use Drupal\helper_module\Attribute\Helper;
use Drupal\Core\StringTranslation\TranslatableMarkup;

#[Helper(
  id: 'example_helper',
  label: new TranslatableMarkup('Example Helper'),
  description: new TranslatableMarkup('Provides example functionality'),
  weight: 0,
  enabled: true
)]
class ExampleHelper extends HelperBase {

  /**
   * Your helper methods here.
   */
  public function doSomething(): void {
    // Helper logic
  }

}
```

### Using Helpers in Code

```php
// Get the helper manager service
$helper_manager = \Drupal::service('plugin.manager.helper');

// Get all enabled helpers
$helpers = $helper_manager->getEnabledHelpers();

// Get a specific helper instance
$example_helper = $helper_manager->createInstance('example_helper');
$example_helper->doSomething();
```

### Using Helpers in hook_form_alter()

```php
use Drupal\Core\Form\FormStateInterface;

/**
 * Implements hook_form_alter().
 */
function your_module_form_alter(&$form, FormStateInterface $form_state, $form_id) {
  if ($form_id === 'node_listing_form' || $form_id === 'node_listing_edit_form') {
    $helper_manager = \Drupal::service('plugin.manager.helper');
    $helper = $helper_manager->createInstance('your_helper_id');
    $helper->alterForm($form, $form_state);
  }
}
```

## Plugin Attributes

The `#[Helper]` attribute accepts the following parameters:

- **id** (required): Unique plugin ID
- **label**: Human-readable name (TranslatableMarkup)
- **description**: Description of what the helper does (TranslatableMarkup)
- **weight**: Sorting weight (default: 0)
- **enabled**: Whether helper is enabled by default (default: true)
- **deriver**: Deriver class for dynamic plugin derivatives (optional)

## Architecture

```
helper-module/
├── helper-module.info.yml          # Module definition
├── helper-module.services.yml      # Service definitions
├── helper-module.module            # Module hooks
└── src/
    ├── Attribute/
    │   └── Helper.php              # Plugin attribute definition
    └── Plugin/
        └── Helper/
            ├── HelperInterface.php # Plugin interface
            ├── HelperBase.php      # Base plugin class
            └── HelperManager.php   # Plugin manager
```

## Best Practices

1. **One Helper Per Feature**: Each helper should handle a single, well-defined feature
2. **Use Services**: Inject dependencies via constructor for testability
3. **Keep .module Small**: Delegate logic to helper plugins, use hooks as thin proxies
4. **Document Your Helpers**: Add clear docblocks explaining what each helper does
5. **Test Your Helpers**: Plugins are classes - write unit tests for them

## When to Create a Separate Module

Create a separate module instead of a helper plugin when:
- The feature can be reused across multiple projects independently
- Different permissions/access control are needed per feature
- The feature is substantial enough to be distributed separately
- Different clients need different combinations of features

## Performance Considerations

Helper Module is designed for performance:
- Single module bootstrap minimizes overhead
- Plugins are lazy-loaded only when needed
- Plugin definitions are cached
- The `getEnabledHelpers()` method allows bulk loading of active helpers

## Maintainer

**IT-WERX**
https://it-werx.ca

## Support

For issues, feature requests, or questions, please contact the maintainer.

## License

GPL-2.0-or-later
