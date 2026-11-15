<?php

declare(strict_types=1);

namespace Drupal\helper_module\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\helper_module\Form\TownSearchForm;
use Drupal\taxonomy\TermInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Province + town search block that goes to the town's page.
 *
 * Place it anywhere in Block layout ("Town search"), or print it from a
 * template with Twig Tweak: drupal_block('helper_module_town_search').
 * On a province page (a top-level canadian_towns term) the form gets that
 * province, so it shows only the Town field.
 */
#[Block(
  id: 'helper_module_town_search',
  admin_label: new TranslatableMarkup('Town search'),
  category: new TranslatableMarkup('Towns Canada'),
)]
class TownSearchBlock extends BlockBase implements ContainerFactoryPluginInterface {

  public function __construct(
  array $configuration,
  $plugin_id,
  $plugin_definition,
  protected FormBuilderInterface $formBuilder,
  protected RouteMatchInterface $routeMatch,
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
    $container->get('form_builder'),
    $container->get('current_route_match'),
  );
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
  // A province page: the canonical page of a canadian_towns term with no
  // parent.
  $province = NULL;
  $term = $this->routeMatch->getParameter('taxonomy_term');
  if ($this->routeMatch->getRouteName() === 'entity.taxonomy_term.canonical'
    && $term instanceof TermInterface && $term->bundle() === 'canadian_towns'
    && (int) $term->get('parent')->target_id === 0) {
    $province = (int) $term->id();
  }
  $build = $this->formBuilder->getForm(TownSearchForm::class, $province);
  $build['#cache']['contexts'][] = 'route';
  return $build;
  }

}
