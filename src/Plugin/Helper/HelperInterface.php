<?php

declare(strict_types=1);

namespace Drupal\helper_module\Plugin\Helper;

use Drupal\Component\Plugin\PluginInspectionInterface;

/**
 * Defines an interface for Helper plugins.
 */
interface HelperInterface extends PluginInspectionInterface {

  /**
   * Gets the helper label.
   *
   * @return string
   *   The helper label.
   */
  public function getLabel(): string;

  /**
   * Gets the helper description.
   *
   * @return string
   *   The helper description.
   */
  public function getDescription(): string;

  /**
   * Gets the helper weight for sorting.
   *
   * @return int
   *   The weight.
   */
  public function getWeight(): int;

  /**
   * Determines if the helper is enabled.
   *
   * @return bool
   *   TRUE if enabled, FALSE otherwise.
   */
  public function isEnabled(): bool;

}
