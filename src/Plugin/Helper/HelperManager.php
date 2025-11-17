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
 */
class HelperManager extends DefaultPluginManager {

    /**
     * Constructs a HelperManager object.
     *
     * @param \Traversable $namespaces
     *   Root paths keyed by namespace to look for plugins.
     * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
     *   Cache backend to use.
     * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
     *   The module handler to invoke alter hooks.
     */
    public function __construct(
        \Traversable $namespaces,
        CacheBackendInterface $cache_backend,
        ModuleHandlerInterface $module_handler
    ) {
        parent::__construct(
            'Plugin/Helper',
            $namespaces,
            $module_handler,
            HelperInterface::class,
            \Drupal\helper_module\Attribute\Helper::class
        );

        // Allow plugins to be altered.
        $this->alterInfo('helper_info');
        $this->setCacheBackend($cache_backend, 'helper_plugins');
    }

    /**
     * Ensure discovery outputs information for debugging.
     *
     * @return array
     *   Plugin definitions sorted by weight.
     */
    public function getDefinitions(): array {
        $definitions = parent::getDefinitions();

        \Drupal::logger('helper_module')->notice('Discovered plugin definitions: @plugins', [
            '@plugins' => print_r($definitions, TRUE),
        ]);

        if (empty($definitions)) {
            // Explicit logging and interruption to detect empty discovery.
            \Drupal::logger('helper_module')->error('No plugins were discovered. Verify discovery paths and `#[Helper]` attribute parsing.');
            throw new \RuntimeException('Empty plugin discovery in HelperManager.');
        }

        return $definitions;
    }
}
