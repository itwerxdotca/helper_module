<?php

declare(strict_types=1);

namespace Drupal\helper_module\Form;

use Drupal\Core\Form\FormAjaxResponseBuilderInterface;
use Drupal\Core\Form\FormStateInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Decorates the core form_ajax_response_builder service to log diagnostic
 * info about the triggering element before delegating to the real builder.
 *
 * Temporary debugging aid - safe to remove once the AJAX callback issue on
 * the point_of_interest_search / listings_search exposed forms is resolved.
 * Does not modify any core file.
 */
class DebugFormAjaxResponseBuilder implements FormAjaxResponseBuilderInterface {

  public function __construct(
    protected FormAjaxResponseBuilderInterface $inner,
    protected LoggerInterface $logger,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function buildResponse(Request $request, array $form, FormStateInterface $form_state, array $commands) {
    $triggering_element = $form_state->getTriggeringElement();

    $callback_info = 'no #ajax key';
    if (isset($triggering_element['#ajax'])) {
      $callback_info = isset($triggering_element['#ajax']['callback'])
        ? (is_string($triggering_element['#ajax']['callback'])
            ? $triggering_element['#ajax']['callback']
            : 'non-string callback: ' . print_r($triggering_element['#ajax']['callback'], TRUE))
        : '#ajax present but no callback key';
    }

    $this->logger->notice('AJAX debug | name: @name | callback: @cb | element keys: @keys', [
      '@name' => $triggering_element['#name'] ?? 'NULL triggering element',
      '@cb' => $callback_info,
      '@keys' => $triggering_element ? implode(',', array_keys($triggering_element)) : 'n/a',
    ]);

    return $this->inner->buildResponse($request, $form, $form_state, $commands);
  }

}
