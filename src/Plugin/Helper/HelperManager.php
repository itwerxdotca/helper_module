<?php

declare(strict_types=1);

namespace Drupal\helper_module\Plugin\Helper;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;

/**
 * Provides a Helper plugin manager.
 *
 * @see \Drupal\helper_module\Plugin\Helper\HelperInterface
 * @see \Drupal\helper_module\Attribute\Helper
 * @see plugin_api
 */
class HelperManager extends DefaultPluginManager {

  /**
   * Constructs a HelperManager object.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler to invoke the alter hook with.
   */
  public function __construct(
    \Traversable $namespaces,
    CacheBackendInterface $cache_backend,
    ModuleHandlerInterface $module_handler,
  ) {
    parent::__construct(
      'Plugin/Helper',
      $namespaces,
      $module_handler,
      HelperInterface::class,
      \Drupal\helper_module\Attribute\Helper::class,
    );

    $this->alterInfo('helper_info');
    $this->setCacheBackend($cache_backend, 'helper_plugins');
  }

  /**
   * Gets all enabled helper plugins sorted by weight.
   *
   * @return \Drupal\helper_module\Plugin\Helper\HelperInterface[]
   *   An array of enabled helper plugin instances, keyed by plugin ID.
   */
  public function getEnabledHelpers(): array {
    $helpers = [];
    foreach ($this->getDefinitions() as $plugin_id => $definition) {
      if ($definition['enabled'] ?? TRUE) {
        $helpers[$plugin_id] = $this->createInstance($plugin_id);
      }
    }

    // Sort by weight.
    uasort($helpers, function (HelperInterface $a, HelperInterface $b) {
      return $a->getWeight() <=> $b->getWeight();
    });

    return $helpers;
  }

}
