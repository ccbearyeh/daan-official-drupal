<?php
declare(strict_types=1);

namespace Drupal\views_pdf\Plugin\views\style;


use Drupal\views\Plugin\views\style\StylePluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

abstract class PDFBaseStyle extends StylePluginBase {
  /** @var \Drupal\views_pdf\Plugin\views\display\PDF */
  public $view;

  protected $usesFields = TRUE;

  /** @var \Symfony\Component\HttpFoundation\Request */
  protected Request $request;

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('request_stack')
    );
  }


  /**
   * @param array $configuration
   * @param $plugin_id
   * @param $plugin_definition
   * @param \Drupal\Core\Http\RequestStack $requestStack
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    RequestStack $requestStack
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->request = $requestStack->getCurrentRequest();
  }

}
