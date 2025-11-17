<?php

declare(strict_types=1);

namespace Drupal\helper_module\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Returns autocomplete responses for cities filtered by province.
 */
class CityAutocompleteController extends ControllerBase {

  /**
   * Returns city autocomplete suggestions filtered by province.
   */
  public function autocomplete(Request $request, string $province): JsonResponse {
    $results = [];
    $input = $request->query->get('q');

    if (!$input || !$province || $province === '0' || $province === '_none') {
      return new JsonResponse($results);
    }

    $query = $this->entityTypeManager()
      ->getStorage('taxonomy_term')
      ->getQuery()
      ->condition('vid', 'canadian_towns')
      ->condition('parent', $province)
      ->condition('name', $input, 'CONTAINS')
      ->range(0, 10)
      ->accessCheck(TRUE)
      ->sort('name', 'ASC');

    $tids = $query->execute();

    if ($tids) {
      $terms = $this->entityTypeManager()
        ->getStorage('taxonomy_term')
        ->loadMultiple($tids);

      foreach ($terms as $term) {
        $results[] = [
          'value' => $term->getName() . ' (' . $term->id() . ')',
          'label' => $term->getName(),
        ];
      }
    }

    return new JsonResponse($results);
  }

}
