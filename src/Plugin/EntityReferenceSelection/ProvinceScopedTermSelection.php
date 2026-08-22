<?php

declare(strict_types=1);

namespace Drupal\helper_module\Plugin\EntityReferenceSelection;

use Drupal\taxonomy\Plugin\EntityReferenceSelection\TermSelection;

/**
 * Provides taxonomy term selection scoped to a parent (province) term.
 *
 * Identical to core's default taxonomy term selection, except when a
 * "parent_term" key is present in #selection_settings, results are
 * restricted to direct children of that term. Used by the province/town
 * cascading search filter so the same "town" entity_autocomplete element
 * Views already builds can be re-scoped per selected province, instead of
 * being replaced by a custom shadow field.
 *
 * @EntityReferenceSelection(
 *   id = "helper_module:province_scoped_term",
 *   label = @Translation("Province-scoped taxonomy term"),
 *   entity_types = {"taxonomy_term"},
 *   group = "helper_module",
 *   weight = 0
 * )
 */
class ProvinceScopedTermSelection extends TermSelection {

  /**
   * {@inheritdoc}
   */
  protected function buildEntityQuery($match = NULL, $match_operator = 'CONTAINS') {
	$query = parent::buildEntityQuery($match, $match_operator);

	$parent_term = $this->getConfiguration()['parent_term'] ?? NULL;
	if (!empty($parent_term)) {
	  $query->condition('parent', (int) $parent_term);
	}

	return $query;
  }

}