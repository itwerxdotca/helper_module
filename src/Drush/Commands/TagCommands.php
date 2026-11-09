<?php

declare(strict_types=1);

namespace Drupal\helper_module\Drush\Commands;

use Drupal\helper_module\Plugin\Helper\HelperManager;
use Drush\Attributes as CLI;
use Drush\Commands\AutowireTrait;
use Drush\Commands\DrushCommands;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Drush commands for tidying up tags (see the "tags" Helper plugin).
 */
final class TagCommands extends DrushCommands {

  use AutowireTrait;

  /**
   * Constructs a TagCommands object.
   */
  public function __construct(
    #[Autowire(service: 'plugin.manager.helper')]
    private readonly HelperManager $helperManager,
  ) {
    parent::__construct();
  }

  /**
   * Lists tags that look like duplicates. Nothing is changed.
   */
  #[CLI\Command(name: 'tags:similar')]
  #[CLI\Usage(name: 'drush tags:similar', description: 'One line per group: each tag with the number of items that use it.')]
  public function similar(): void {
    $groups = $this->helperManager->createInstance('tags')->similarGroups();
    if (!$groups) {
      $this->io()->writeln('No tags look like duplicates.');
      return;
    }
    foreach ($groups as $members) {
      $this->io()->writeln(implode(' | ', array_map(fn(array $tag) => sprintf('%s (%d)', $tag['name'], $tag['items']), $members)));
    }
  }

  /**
   * Merges one tag into another: retags the content, deletes the old tag and redirects its page.
   */
  #[CLI\Command(name: 'tags:merge')]
  #[CLI\Argument(name: 'from', description: 'The tag to merge away, by its exact name.')]
  #[CLI\Argument(name: 'to', description: 'The tag to keep, by its exact name.')]
  #[CLI\Usage(name: 'drush tags:merge hiking Trails', description: 'Moves everything tagged "hiking" to "Trails", deletes "hiking" and redirects /tag/hiking.')]
  public function merge(string $from, string $to): void {
    foreach ($this->helperManager->createInstance('tags')->merge($from, $to) as $line) {
      $this->io()->writeln($line);
    }
  }

}
