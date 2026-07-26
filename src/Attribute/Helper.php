<?php

declare(strict_types=1);

namespace Drupal\helper_module\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines a Helper attribute for plugin discovery.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class Helper extends Plugin {

  /**
   * Constructs a Helper attribute.
   *
   * @param string $id
   *   The plugin ID.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|null $label
   *   The human-readable label of the helper.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|null $description
   *   The description of the helper.
   * @param int $weight
   *   The weight for sorting helpers.
   * @param bool $enabled
   *   Whether this helper is enabled by default.
   * @param class-string|null $deriver
   *   The deriver class.
   */
  public function __construct(
    public readonly string $id,
    public readonly ?TranslatableMarkup $label = NULL,
    public readonly ?TranslatableMarkup $description = NULL,
    public readonly int $weight = 0,
    public readonly bool $enabled = TRUE,
    public readonly ?string $deriver = NULL,
  ) {}

}
