<?php

namespace Drupal\contribute\Plugin\Block;

use Drupal\contribute\ContributeManagerInterface;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'Community Information' block.
 *
 * @Block(
 *   id = "community_information_block",
 *   admin_label = @Translation("Community Information block"),
 *   category = @Translation("Community Information Block")
 * )
 */
class CommunityInformationBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The contribute manager.
   *
   * @var \Drupal\contribute\ContributeManagerInterface
   */
  protected $contributeManager;

  /**
   * Creates a CommunityInformationBlock instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\contribute\ContributeManagerInterface $contribute_manager
   *   The contribute manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ContributeManagerInterface $contribute_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->contributeManager = $contribute_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('contribute.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build = [];
    if ($this->contributeManager->getStatus()) {
      $build['#theme'] = 'contribute_status_report_community_info';
      $build['#account'] = $this->contributeManager->getAccount(FALSE);
      $build['#membership'] = $this->contributeManager->getMembership();
      $build['#contribution'] = $this->contributeManager->getContribution();
    }
    return $build;
  }

}
