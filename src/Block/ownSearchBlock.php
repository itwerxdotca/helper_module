<?php

declare(strict_types=1);

namespace Drupal\helper_module\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\helper_module\Form\TownSearchForm;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Province + town search block that goes to the town's page.
 *
 * Place it anywhere in Block layout ("Town search"), or print it from a
 * template with Twig Tweak: drupal_block('helper_module_town_search').
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
	);
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
	return $this->formBuilder->getForm(TownSearchForm::class);
  }

}