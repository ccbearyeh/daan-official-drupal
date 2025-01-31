<?php
declare(strict_types=1);

namespace Drupal\views_pdf\Plugin\views_pdf\font;

use Drupal\Core\Plugin\PluginBase as ComponentPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

abstract class FontPluginBase extends ComponentPluginBase {

  /**
   * Plugins's definition.
   *
   * @var array
   */
  public $definition;


  /**
   * Constructs a PluginBase object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->definition = $plugin_definition + $configuration;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($configuration, $plugin_id, $plugin_definition);
  }

}
