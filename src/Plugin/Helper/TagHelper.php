<?php

declare(strict_types=1);

namespace Drupal\helper_module\Plugin\Helper;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\helper_module\Attribute\Helper;
use Drupal\node\NodeInterface;
use Drupal\path_alias\AliasManagerInterface;
use Drupal\redirect\Entity\Redirect;
use Drupal\taxonomy\TermInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Keeps the tags vocabulary free of duplicates with plain string rules.
 *
 * - Tag names are saved with single spaces and no spaces at the ends
 *   (tidyName(), from helper_module_taxonomy_term_presave()).
 * - When content is saved, a tag that differs from an older tag only by
 *   capitals, spacing or a plural ending ("Trails" and "trail") is swapped
 *   for the older tag (useExistingTags(), from helper_module_node_presave()).
 * - Looser matches (spelling distance, sounds-alike, shared stem) are only
 *   listed for review (similarGroups(), drush tags:similar), because they
 *   also pair different words such as "hiking" and "biking". The ones you
 *   choose are merged with merge() (drush tags:merge).
 */
#[Helper(
  id: 'tags',
  label: new TranslatableMarkup('Tag tidy-up'),
  description: new TranslatableMarkup('Reuses existing tags for capital, spacing and plural variants; lists and merges likely duplicates.'),
  weight: 20,
)]
class TagHelper extends HelperBase implements ContainerFactoryPluginInterface {

  /**
   * The tags vocabulary.
   */
  public const VOCABULARY = 'tags';

  /**
   * The tag field, shared by every content type that has tags.
   */
  public const FIELD = 'field_listing_tags';

  /**
   * Constructs a TagHelper object.
   */
  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly Connection $database,
    private readonly AliasManagerInterface $aliasManager,
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
      $container->get('database'),
      $container->get('path_alias.manager'),
    );
  }

  /**
   * Returns the key two tag names are compared by.
   *
   * Lower case, single spaces, and the last word made singular by plain
   * English rules: "Trails" and "trail" both give "trail", "Accessories"
   * gives "accessory", "Live  Music" gives "live music".
   */
  public function key(string $name): string {
    $key = mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $name)));
    return (string) preg_replace(['/ies$/u', '/(ss|x|z|ch|sh)es$/u', '/(?<!s)s$/u'], ['y', '$1', ''], $key);
  }

  /**
   * Saves a tag's name with single spaces and no spaces at the ends.
   */
  public function tidyName(TermInterface $term): void {
    if ($term->bundle() !== self::VOCABULARY) {
      return;
    }
    $name = trim((string) preg_replace('/\s+/u', ' ', $term->getName()));
    if ($name !== $term->getName()) {
      $term->setName($name);
    }
  }

  /**
   * Swaps a node's tags for older tags with the same key.
   *
   * A new tag typed as "Trails" when "trail" exists becomes "trail". The
   * swapped-out tag is deleted (its page redirected) when no other content
   * uses it; otherwise it is left for drush tags:merge.
   */
  public function useExistingTags(NodeInterface $node): void {
    if (!$node->hasField(self::FIELD) || $node->get(self::FIELD)->isEmpty()) {
      return;
    }

    $tags = $this->loadTagNames();
    $tids = [];
    $changed = FALSE;
    foreach ($node->get(self::FIELD) as $item) {
      $tid = (int) $item->target_id;
      $term = $item->entity;
      if ($term instanceof TermInterface && $term->bundle() === self::VOCABULARY) {
        $older = $this->findOlder($term, $tags);
        if ($older) {
          $changed = TRUE;
          if (!$this->usedElsewhere($tid, (int) $node->id())) {
            $this->removeTag($term, $older);
          }
          $tid = (int) $older->id();
        }
      }
      if (in_array($tid, $tids, TRUE)) {
        $changed = TRUE;
        continue;
      }
      $tids[] = $tid;
    }

    if ($changed) {
      $node->set(self::FIELD, $tids);
    }
  }

  /**
   * Returns groups of tags that look like duplicates, for review.
   *
   * Two tags are grouped when they have the same key, sound alike
   * (metaphone), are within two letters of each other (levenshtein, names
   * of five letters or more), or are single words sharing their first six
   * letters (history, historical, Historic).
   *
   * @return array
   *   Groups of two or more tags, each a list of ['tid', 'name', 'items'],
   *   where 'items' is the number of nodes that use the tag.
   */
  public function similarGroups(): array {
    $tags = $this->loadTagNames();
    $tids = array_keys($tags);

    // Union-find: each tag points to the first tag of its group.
    $group = array_combine($tids, $tids);
    $find = function (int $tid) use (&$group): int {
      while ($group[$tid] !== $tid) {
        $tid = $group[$tid];
      }
      return $tid;
    };
    foreach ($tids as $i => $a) {
      foreach (array_slice($tids, $i + 1) as $b) {
        if ($this->looksAlike($tags[$a], $tags[$b])) {
          $group[$find($b)] = $find($a);
        }
      }
    }

    $counts = $this->database->query('SELECT field_listing_tags_target_id, COUNT(DISTINCT entity_id) FROM {node__field_listing_tags} GROUP BY field_listing_tags_target_id')->fetchAllKeyed();
    $groups = [];
    foreach ($tids as $tid) {
      $groups[$find($tid)][] = [
        'tid' => $tid,
        'name' => $tags[$tid],
        'items' => (int) ($counts[$tid] ?? 0),
      ];
    }
    return array_values(array_filter($groups, fn(array $members) => count($members) > 1));
  }

  /**
   * Merges one tag into another, by name.
   *
   * Moves the content tagged $from_name to $to_name (without doubling it
   * where both are set), deletes $from_name and redirects its page to
   * $to_name's.
   *
   * @return string[]
   *   What was done, one line per step.
   */
  public function merge(string $from_name, string $to_name): array {
    $from = $this->loadTagByName($from_name);
    $to = $this->loadTagByName($to_name);
    if (!$from || !$to) {
      return [sprintf('No tag named "%s".', $from ? $to_name : $from_name)];
    }
    if ($from->id() === $to->id()) {
      return ['Those are the same tag.'];
    }

    $lines = [];
    $node_storage = $this->entityTypeManager->getStorage('node');
    $nids = $node_storage->getQuery()
      ->accessCheck(FALSE)
      ->condition(self::FIELD, $from->id())
      ->execute();
    foreach ($node_storage->loadMultiple($nids) as $node) {
      $tids = [];
      foreach ($node->get(self::FIELD) as $item) {
        $tid = (int) $item->target_id === (int) $from->id() ? (int) $to->id() : (int) $item->target_id;
        if (!in_array($tid, $tids, TRUE)) {
          $tids[] = $tid;
        }
      }
      $node->set(self::FIELD, $tids);
      $node->save();
      $lines[] = sprintf('retagged: %d %s', $node->id(), $node->label());
    }

    $lines = array_merge($lines, $this->removeTag($from, $to));
    return $lines;
  }

  /**
   * Returns the names of all tags, keyed by term ID, oldest first.
   *
   * @return string[]
   *   Tag names keyed by term ID.
   */
  private function loadTagNames(): array {
    return array_map('strval', $this->database
      ->query('SELECT tid, name FROM {taxonomy_term_field_data} WHERE vid = :vid AND default_langcode = 1 ORDER BY tid', [':vid' => self::VOCABULARY])
      ->fetchAllKeyed());
  }

  /**
   * Loads a tag by its exact name.
   */
  private function loadTagByName(string $name): ?TermInterface {
    $tid = array_search($name, $this->loadTagNames(), TRUE);
    return $tid === FALSE ? NULL : $this->entityTypeManager->getStorage('taxonomy_term')->load($tid);
  }

  /**
   * Returns the oldest other tag with the same key as $term, if there is one.
   */
  private function findOlder(TermInterface $term, array $tags): ?TermInterface {
    $key = $this->key($term->getName());
    foreach ($tags as $tid => $name) {
      if ($tid >= (int) $term->id()) {
        break;
      }
      if ($this->key($name) === $key) {
        return $this->entityTypeManager->getStorage('taxonomy_term')->load($tid);
      }
    }
    return NULL;
  }

  /**
   * Checks whether a tag is used by any node other than $nid.
   */
  private function usedElsewhere(int $tid, int $nid): bool {
    return (bool) $this->database
      ->query('SELECT 1 FROM {node__field_listing_tags} WHERE field_listing_tags_target_id = :tid AND entity_id <> :nid LIMIT 1', [':tid' => $tid, ':nid' => $nid])
      ->fetchField();
  }

  /**
   * Deletes a tag and redirects its page to another tag's page.
   *
   * @return string[]
   *   What was done.
   */
  private function removeTag(TermInterface $old, TermInterface $keep): array {
    $old_path = '/taxonomy/term/' . $old->id();
    $alias = $this->aliasManager->getAliasByPath($old_path);
    $name = $old->getName();
    $old->delete();
    $lines = [sprintf('deleted tag: %s (%d)', $name, $old->id())];

    if ($alias !== $old_path && class_exists(Redirect::class)) {
      $redirect = Redirect::create();
      $redirect->setSource(ltrim($alias, '/'));
      $redirect->setRedirect('/taxonomy/term/' . $keep->id());
      $redirect->setLanguage('und');
      $redirect->setStatusCode(301);
      $redirect->save();
      $lines[] = sprintf('redirect: %s -> %s', $alias, $this->aliasManager->getAliasByPath('/taxonomy/term/' . $keep->id()));
    }
    return $lines;
  }

  /**
   * Checks whether two tag names look like the same tag.
   */
  private function looksAlike(string $a, string $b): bool {
    $key_a = $this->key($a);
    $key_b = $this->key($b);
    if ($key_a === $key_b) {
      return TRUE;
    }
    $sound_a = metaphone($key_a);
    if ($sound_a !== '' && $sound_a === metaphone($key_b)) {
      return TRUE;
    }
    if (min(strlen($key_a), strlen($key_b)) >= 5 && levenshtein($key_a, $key_b) <= 2) {
      return TRUE;
    }
    if (!str_contains($key_a, ' ') && !str_contains($key_b, ' ')) {
      return strncmp($key_a, $key_b, 6) === 0 && min(strlen($key_a), strlen($key_b)) >= 6;
    }
    return FALSE;
  }

}
