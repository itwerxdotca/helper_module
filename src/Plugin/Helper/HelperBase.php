<?php

declare(strict_types=1);

namespace Drupal\helper_module\Plugin\Helper;

use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Base class for Helper plugins.
 */
abstract class HelperBase extends PluginBase implements HelperInterface {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function getLabel(): string {
    return (string) ($this->pluginDefinition['label'] ?? $this->getPluginId());
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription(): string {
    return (string) ($this->pluginDefinition['description'] ?? '');
  }

  /**
   * {@inheritdoc}
   */
  public function getWeight(): int {
    return $this->pluginDefinition['weight'] ?? 0;
  }

  /**
   * {@inheritdoc}
   */
  public function isEnabled(): bool {
    return $this->pluginDefinition['enabled'] ?? TRUE;
  }

}
