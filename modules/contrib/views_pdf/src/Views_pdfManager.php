<?php
declare(strict_types=1);

namespace Drupal\views_pdf;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;

/**
 * Plugin Views PDF Font provider
 *
 * @see \Drupal\views_pdf\Annotation\Views_pdfFont
 * @see plugin_api
 */
class Views_pdfManager extends DefaultPluginManager {

  /**
   *
   */
  public function __construct(
    $type,
    \Traversable $namespaces,
    CacheBackendInterface $cache_backend,
    ModuleHandlerInterface $module_handler
  ) {
    parent::__construct(
      'Plugin/views_pdf/'.$type,
      $namespaces,
      $module_handler,
      'Drupal\views_pdf\Plugin\Views_pdfFontManagerInterface',
      'Drupal\Component\Annotation\Plugin',
      ['Drupal\views_pdf\Annotation']
    );

    $this->alterInfo('views_pdf_'.$type);
    $this->setCacheBackend($cache_backend, 'views_pdf:' . $type);
  }
}
