<?php

declare(strict_types=1);

namespace Drupal\helper_module\Plugin\Helper;

use Drupal\helper_module\Attribute\Helper;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\taxonomy\TermInterface;

/**
 * Helper for province/town listing category routing and URL generation.
 */
#[Helper(
  id: 'provincetownlistingcategoryhelper',
  label: new TranslatableMarkup('Province/Town Listing Category Helper'),
  description: new TranslatableMarkup('Handles hierarchical listing category URLs with province/town prefixes'),
  weight: 0,
  enabled: true
)]
class ProvinceTownListingCategoryHelper extends HelperBase {

  /**
   * The view machine name for listings.
   */
  private const VIEW_NAME = 'taxonomy_blocks';

  /**
   * The view display ID.
   */
  private const DISPLAY_ID = 'block_1';

  /**
   * Cached view argument configuration.
   */
  private ?array $viewArgumentConfig = NULL;

  /**
   * Get contextual filter configuration from the view.
   *
   * This introspects the view to discover which vocabularies are used
   * in each contextual filter position, eliminating hardcoded vocab names.
   *
   * @return array
   *   Array with keys:
   *   - 'arguments': Ordered list of argument handlers
   *   - 'vocabularies': Vocabulary machine names by argument position
   *   - 'argument_types': Type of each argument (taxonomy, geolocation, etc.)
   */
  private function getViewArgumentConfig(): array {
    // Return cached config if available
    if ($this->viewArgumentConfig !== NULL) {
      return $this->viewArgumentConfig;
    }

    $view = \Drupal\views\Views::getView(self::VIEW_NAME);

    if (!$view) {
      throw new \Exception('View not found: ' . self::VIEW_NAME);
    }

    $view->setDisplay(self::DISPLAY_ID);
    $view->initHandlers();

    $config = [
      'arguments' => [],
      'vocabularies' => [],
      'argument_types' => [],
    ];

    // Get all contextual filters (arguments) from the view
    $arguments = $view->display_handler->getHandlers('argument');

    $position = 0;
    foreach ($arguments as $argument_id => $argument) {
      $config['arguments'][$position] = $argument_id;

      // Detect taxonomy arguments
      if ($argument instanceof \Drupal\taxonomy\Plugin\views\argument\IndexTid ||
          $argument instanceof \Drupal\taxonomy\Plugin\views\argument\IndexTidDepth) {

        $config['argument_types'][$position] = 'taxonomy';

        // Extract vocabulary from the argument's validation settings
        $options = $argument->options;
        if (isset($options['validate_options']['bundles'])) {
          $bundles = array_keys($options['validate_options']['bundles']);
          // Store the first vocabulary (usually only one per argument)
          $config['vocabularies'][$position] = reset($bundles);
        }
      }
      // Detect geolocation arguments
      elseif (strpos(get_class($argument), 'Geolocation') !== FALSE) {
        $config['argument_types'][$position] = 'geolocation';
        $config['vocabularies'][$position] = NULL; // No vocabulary for geolocation
      }
      // Other argument types
      else {
        $config['argument_types'][$position] = 'other';
        $config['vocabularies'][$position] = NULL;
      }

      $position++;
    }

    // Cache the configuration
    $this->viewArgumentConfig = $config;

    return $config;
  }

  /**
   * Get vocabulary for a specific argument position.
   *
   * @param int $position
   *   The argument position (0-indexed).
   *
   * @return string|null
   *   The vocabulary machine name, or NULL if not a taxonomy argument.
   */
  private function getVocabularyByPosition(int $position): ?string {
    $config = $this->getViewArgumentConfig();
    return $config['vocabularies'][$position] ?? NULL;
  }

  /**
   * Find which argument position uses a specific vocabulary pattern.
   *
   * @param string $pattern
   *   Pattern to search for in vocabulary name (e.g., 'town', 'province').
   *
   * @return int|null
   *   The argument position, or NULL if not found.
   */
  private function findArgumentPositionByPattern(string $pattern): ?int {
    $config = $this->getViewArgumentConfig();

    foreach ($config['vocabularies'] as $position => $vocab_id) {
      if ($vocab_id && stripos($vocab_id, $pattern) !== FALSE) {
        return $position;
      }
    }

    return NULL;
  }

  /**
   * Render the view for a taxonomy term.
   *
   * @param \Drupal\taxonomy\TermInterface $term
   *   The listing category taxonomy term.
   * @param string $province
   *   The province slug from the URL.
   * @param string $town
   *   The town slug from the URL.
   *
   * @return array
   *   Render array for the view display.
   */
  public function renderView(TermInterface $term, string $province, string $town): array {
    $view = \Drupal\views\Views::getView(self::VIEW_NAME);

    if (!$view) {
      throw new \Exception('View not found: ' . self::VIEW_NAME);
    }

    // Get the view's argument configuration
    $arg_config = $this->getViewArgumentConfig();

    // Build arguments array based on view configuration
    $arguments = [];

    foreach ($arg_config['argument_types'] as $position => $type) {
      if ($type === 'taxonomy') {
        $vocab = $arg_config['vocabularies'][$position];

        // Determine which term to pass based on vocabulary
        if (stripos($vocab, 'town') !== FALSE || stripos($vocab, 'city') !== FALSE) {
          // This is the town argument
          $town_tid = $this->findTermBySlug($town, $vocab, $province);
          $arguments[$position] = $town_tid;
        }
        elseif ($position === 0) {
          // First argument is usually the listing category
          $arguments[$position] = $term->id();
        }
        else {
          // Unknown taxonomy argument - pass 'all'
          $arguments[$position] = 'all';
        }
      }
      elseif ($type === 'geolocation') {
        // Skip geolocation filter
        $arguments[$position] = 'all';
      }
      else {
        // Other argument types - pass 'all'
        $arguments[$position] = 'all';
      }
    }

    $view->setDisplay(self::DISPLAY_ID);
    $view->setArguments($arguments);
    $view->preExecute();
    $view->execute();

    // Store province/town in tempstore for breadcrumbs
    $tempstore = \Drupal::service('tempstore.private')->get('helper_module');
    $tempstore->set('current_province', $province);
    $tempstore->set('current_town', $town);

    return [
      '#type' => 'view',
      '#name' => self::VIEW_NAME,
      '#display_id' => self::DISPLAY_ID,
      '#arguments' => $arguments,
      '#embed' => TRUE,
    ];
  }

  /**
   * Find taxonomy term by slug in a specific vocabulary.
   *
   * @param string $slug
   *   The URL slug to search for.
   * @param string $vocabulary
   *   The vocabulary machine name to search in.
   * @param string|null $parent_slug
   *   Optional parent slug to filter by (for hierarchical lookups).
   *
   * @return int|null
   *   The term ID, or NULL if not found.
   */
  private function findTermBySlug(string $slug, string $vocabulary, ?string $parent_slug = NULL): ?int {
    $term_storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');

    // If parent_slug provided, find parent first
    $parent_tid = NULL;
    if ($parent_slug) {
      // Find which vocabulary contains provinces/parents
      $config = $this->getViewArgumentConfig();
      foreach ($config['vocabularies'] as $vocab) {
        if ($vocab && (stripos($vocab, 'province') !== FALSE || stripos($vocab, 'state') !== FALSE)) {
          $parent_tid = $this->findTermBySlug($parent_slug, $vocab);
          break;
        }
      }
    }

    // Query for terms
    $query = $term_storage->getQuery()
      ->condition('vid', $vocabulary)
      ->accessCheck(TRUE);

    if ($parent_tid) {
      $query->condition('parent', $parent_tid);
    }

    $tids = $query->execute();

    // Match by slug
    foreach ($tids as $tid) {
      $term = $term_storage->load($tid);
      if ($term && $this->generateSlug($term->getName()) === $slug) {
        return (int) $tid;
      }
    }

    return NULL;
  }

  /**
   * Find taxonomy term by hierarchical slug path.
   *
   * @param array $slugs
   *   Array of URL slugs.
   * @param int $parent_id
   *   Parent term ID.
   *
   * @return \Drupal\taxonomy\TermInterface|null
   *   The found term or NULL.
   */
  public function findTermByHierarchy(array $slugs, int $parent_id = 0): ?TermInterface {
    if (empty($slugs)) {
      return NULL;
    }

    // Get the listing category vocabulary from view config (position 0)
    $listing_vocab = $this->getVocabularyByPosition(0);

    if (!$listing_vocab) {
      throw new \Exception('Could not determine listing category vocabulary from view');
    }

    $current_slug = strtolower(array_shift($slugs));

    $term_storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
    $query = $term_storage->getQuery()
      ->condition('vid', $listing_vocab)
      ->accessCheck(TRUE);

    if ($parent_id) {
      $query->condition('parent', $parent_id);
    }

    $tids = $query->execute();

    foreach ($tids as $tid) {
      $term = $term_storage->load($tid);
      if (!$term instanceof TermInterface) {
        continue;
      }

      $term_slug = $this->generateSlug($term->getName());

      if ($term_slug === $current_slug) {
        if (!empty($slugs)) {
          return $this->findTermByHierarchy($slugs, (int) $tid);
        }
        return $term;
      }
    }

    return NULL;
  }

  /**
   * Generate URL slug from term name.
   *
   * @param string $name
   *   The term name.
   *
   * @return string
   *   The URL slug.
   */
  public function generateSlug(string $name): string {
    return strtolower(preg_replace('/[^\w]+/', '-', trim($name)));
  }

}
