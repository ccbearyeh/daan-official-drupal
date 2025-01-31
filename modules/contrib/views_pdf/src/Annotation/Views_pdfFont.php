<?php
declare(strict_types=1);

namespace Drupal\views_pdf\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a Plugin annotation object for views_pdf font plugins.
 *
 * @see \Drupal\views_pdf\Plugin\views_pdf\font\FontPluginBase
 *
 * @Annotation
 */
class Views_pdfFont extends Plugin {

  /**
   * ID to map the font for instance, use calibri instead Calibri.
   *
   * @var string
   */
  public $id;

  /**
   * Label to show in selection list.
   *
   * @var string
   */
  public $label;

  /**
   * Direct font file, example: Calibri.ttf
   *
   * @var string
   */
  public $font_file;

  /**
   * Font styles supported.
   *
   * @code
   * font_styles = {
   *  "b",
   *  "i",
   * }
   * @endcode
   *
   * @var string[]
   */
  public $font_styles = [];

  /**
   * Base directory to look for fonts, where the root directory will be
   * the module path itself.
   *
   * Think of <custom_module_path>/<base_dir> structure
   *
   * Default value: fonts
   *
   * @var string
   */
  public $base_dir = 'fonts';

}
