<?php
declare(strict_types=1);

namespace Drupal\views_pdf\Plugin\views\row;

use Drupal\Component\Utility\Html;
use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\row\RowPluginBase;
use Drupal\views_pdf\PdfLibrary\FPDI;

/**
 * The basic 'fields' row plugin.
 *
 * This displays fields one after another, giving options for inline
 * or not.
 *
 * @ingroup views_row_plugins
 *
 * @property FPDI $pdf
 *
 * @ViewsRow(
 *   id = "pdf_fields",
 *   title = @Translation("PDF Fields"),
 *   help = @Translation("Displays the fields with configurable co-ordinates."),
 *   register_theme = FALSE,
 *   display_types = {"pdf"}
 * )
 */
class PDFFields extends RowPluginBase {

  /**
   * {@inheritdoc}
   */
  protected $usesFields = TRUE;

  public FPDI $pdf;

  /**
   * @staticvar integer $view this is actually what is returned
   *
   * @param \Drupal\views\ResultRow $row
   *
   * @return string|void
   */
  public function render($row) {
    $this->pdf = $this->view->pdf;

    foreach ($this->view->field as $id => $field) {
      if (empty($field->options['exclude'])) {
        $options = $this->options['formats'][$id] ?? [];

        switch ($this->view->getStyle()->pluginId) {
          case 'pdf_unformatted':
            // Register the row for header & footer on the current page before writing
            // each field. This is necessary in case the fields for one record span
            // multiple pages, or there is a page break. Otherwise there can be pages
            // with missing headers and footers.
            $this->pdf->setHeaderFooter($row, $this->options, $this->view);

            $this->pdf->drawContent($row, $options, $this->view, $id);

            break;
          case 'pdf_grid':
            $options['grid'] = $this->options['grid'];
            $this->pdf->drawContent($row, $options, $this->view, $id);
            $this->options['grid']['new_cell'] = FALSE;
            break;
        }
      }
    }

    // Reset the row page number.
    $this->view->pdf->resetRowPageNumber();
  }

  /**
   * {@inheritDoc}
   */
  public function defineOptions() {
    $options = parent::defineOptions();

    $options['formats'] = ['default' => []];
    return $options;
  }

  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    $options = $this->displayHandler->getFieldLabels();
    $fields = $this->displayHandler->getOption('fields');

    $all_options = $this->view->getStyle()->pluginId !== 'pdf_grid';

    $fonts = array_merge(['default' => t('-- Default --')], FPDI::getAvailableFontsCleanList());
    $font_styles = [
      'b' => t('Bold'),
      'i' => t('Italic'),
      'u' => t('Underline'),
      'd' => t('Line through'),
      'o' => t('Overline'),
    ];

    $relativeElements = [
      'page' => t('Page'),
      'header_footer' => t('In header / footer'),
      'last_position' => t('Last Writing Position'),
      'self' => t('Field: Self'),
    ];
    if (!$all_options) {
      $relativeElements['page'] = t('Grid cell');
      unset($relativeElements['header_footer']);
    }

    $align = [
      'L' => t('Left'),
      'C' => t('Center'),
      'R' => t('Right'),
      'J' => t('Justify'),
    ];

    $hyphenate = [
      'none' => t('None'),
      'auto' => t('Detect automatically'),
    ];
    // TODO: New Entity TYpe Hyphenate.
    $hyphenate = array_merge($hyphenate, []);


    if (empty($this->options['inline'])) {
      $this->options['inline'] = [];
    }

    $form['formats']['heading'] = [
      '#type' => 'markup',
      '#prefix' => '<h4>',
      '#markup' => t('Enter field-specific style and position settings below'),
      '#suffix' => '</h4>',
    ];
    $keepDetailsOpen = TRUE;
    foreach ($options as $field => $option) {

      if (!empty($fields[$field]['exclude'])) {
        continue;
      }

      $form['formats'][$field] = [
        '#type' => 'details',
        '#title' => Html::escape($option),
        '#open' => $keepDetailsOpen,
      ];
      $keepDetailsOpen = FALSE;

      $form['formats'][$field]['position'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Position Settings'),
        '#collapsed' => TRUE,
        '#collapsible' => TRUE,
      ];

      $form['formats'][$field]['position']['object'] = [
        '#type' => 'select',
        '#title' => $this->t('Position relative to'),
        '#required' => FALSE,
        '#options' => $relativeElements,
        '#default_value' => $this->options['formats'][$field]['position']['object'] ?? 'last_position',
      ];

      $form['formats'][$field]['position']['corner'] = [
        '#type' => 'radios',
        '#title' => $this->t('Position relative to corner'),
        '#required' => FALSE,
        '#options' => [
          'top_left' => $this->t('Top Left'),
          'top_right' => $this->t('Top Right'),
          'bottom_left' => $this->t('Bottom Left'),
          'bottom_right' => $this->t('Bottom Right'),
        ],
        '#default_value' => $this->options['formats'][$field]['position']['corner'] ?? 'top_left',
      ];

      $relativeElements['field_' . $field] = $this->t('Field: :field', [':field' => $option]);


      $form['formats'][$field]['position']['x'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Position X'),
        '#required' => FALSE,
        '#default_value' => $this->options['formats'][$field]['position']['x'] ?? '',
      ];

      $form['formats'][$field]['position']['y'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Position Y'),
        '#required' => FALSE,
        '#default_value' => $this->options['formats'][$field]['position']['y'] ?? '',
      ];

      $form['formats'][$field]['position']['width'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Width'),
        '#required' => FALSE,
        '#default_value' => $this->options['formats'][$field]['position']['width'] ?? '',
      ];

      $form['formats'][$field]['position']['height'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Height'),
        '#required' => FALSE,
        '#default_value' => $this->options['formats'][$field]['position']['height'] ?? '',
      ];

      $form['formats'][$field]['text'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Text Settings'),
        '#collapsed' => TRUE,
        '#collapsible' => TRUE,
      ];

      $form['formats'][$field]['text']['font_size'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Font Size'),
        '#size' => 10,
        '#default_value' => $this->options['formats'][$field]['text']['font_size'] ?? '',
      ];
      $form['formats'][$field]['text']['font_family'] = [
        '#type' => 'select',
        '#title' => $this->t('Font Family'),
        '#required' => TRUE,
        '#options' => $fonts,
        '#size' => 5,
        '#default_value' => $this->options['formats'][$field]['text']['font_family'] ?? 'default',
      ];
      $form['formats'][$field]['text']['font_style'] = [
        '#type' => 'checkboxes',
        '#title' => $this->t('Font Style'),
        '#options' => $font_styles,
        '#size' => 10,
        '#default_value' => $this->options['formats'][$field]['text']['font_style'] ?? $this->displayHandler->getOption('default_font_style'),
      ];
      $form['formats'][$field]['text']['align'] = [
        '#type' => 'radios',
        '#title' => $this->t('Alignment'),
        '#options' => $align,
        '#default_value' => $this->options['formats'][$field]['text']['align'] ?? $this->displayHandler->getOption('default_text_align'),
      ];
      $form['formats'][$field]['text']['hyphenate'] = [
        '#type' => 'select',
        '#title' => $this->t('Text Hyphenation'),
        '#options' => $hyphenate,
        '#description' => $this->t('If you want to use hyphenation, then you need to download from <a href="@url">ctan.org</a> your needed pattern set. Then upload it to the dir "hyphenate_patterns" in the TCPDF lib directory. Perhaps you need to create the dir first. If you select the automated detection, then we try to get the language of the current node and select an appropriate hyphenation pattern.', ['@url' => 'http://www.ctan.org/tex-archive/language/hyph-utf8/tex/generic/hyph-utf8/patterns/tex']),
        '#default_value' => $this->options['formats'][$field]['text']['hyphenate'] ?? $this->displayHandler->getOption('default_text_hyphenate'),
      ];
      $form['formats'][$field]['text']['color'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Text Color'),
        '#description' => $this->t('If a value is entered without a comma, it will be interpreted as a hexadecimal RGB color. Normal RGB can be used by separating the components by a comma. e.g 255,255,255 for white. A CMYK color can be entered in the same way as RGB. e.g. 0,100,0,0 for magenta.'),
        '#size' => 20,
        '#default_value' => $this->options['formats'][$field]['text']['color'] ?? $this->displayHandler->getOption('default_text_color'),
      ];
      $form['formats'][$field]['render'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Render Settings'),
        '#collapsed' => TRUE,
        '#collapsible' => TRUE,
      ];

      if ($all_options) {
        $form['formats'][$field]['render']['is_html'] = [
          '#type' => 'checkbox',
          '#title' => $this->t('Render As HTML'),
          '#default_value' => $this->options['formats'][$field]['render']['is_html'] ?? 1,
        ];
        $form['formats'][$field]['render']['minimal_space'] = [
          '#type' => 'textfield',
          '#title' => $this->t('Minimal Space'),
          '#description' => $this->t('Specify here the minimal space, which is needed on the page, that the content is placed on the page.'),
          '#default_value' => $this->options['formats'][$field]['render']['minimal_space'] ?? 1,
        ];
      }

      $form['formats'][$field]['render']['custom_layout'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Enable custom layout hook.'),
        '#description' => $this->t("Allow a custom module to alter the layout of this field using hook_views_pdf_custom_layout()."),
        '#default_value' => $this->options['formats'][$field]['render']['custom_layout'] ?? FALSE,
      ];
      $form['formats'][$field]['render']['custom_post'] = [
        '#type' => 'checkbox',
        '#title' => t('Enable custom post render hook.'),
        '#description' => t("Allow a custom module to execute after the rendering of this field using hook_views_pdf_custom_post()."),
        '#default_value' => $this->options['formats'][$field]['render']['custom_post'] ?? FALSE,
      ];
      if (defined('VIEWS_PDF_PHP') && user_access('use PHP for settings')) {
        $form['formats'][$field]['render']['use_php'] = [
          '#type' => 'checkbox',
          '#title' => $this->t('Enable direct PHP code.'),
          '#description' => $this->t("Show input boxes for direct PHP code (not recommended)."),
          '#default_value' => !empty($this->options['formats'][$field]['render']['use_php']) ?
            $this->options['formats'][$field]['render']['use_php'] :
            !empty($this->options['formats'][$field]['render']['eval_before']) ||
            !empty($this->options['formats'][$field]['render']['eval_after']),
        ];
        $form['formats'][$field]['render']['eval_before'] = [
          '#type' => 'textarea',
          '#title' => $this->t('PHP Code Before Output'),
          '#description' =>
            t('Please avoid direct PHP here, this feature is deprecated in favour of the custom layout hook'),
          '#default_value' => $this->options['formats'][$field]['render']['eval_before'] ?? '',
          '#states' => [
            'visible' => [
              ":input[name=\"row_options[formats][$field][render][use_php]\"]" => ['checked' => TRUE],
            ],
          ],
        ];
        $form['formats'][$field]['render']['bypass_eval_before'] = [
          '#type' => 'checkbox',
          '#title' => $this->t('Use the PHP eval function instead php_eval.'),
          '#description' => $this->t("WARNING: If you don't know the risk of using eval leave unckecked."),
          '#default_value' => !empty($this->options['formats'][$field]['render']['bypass_eval_before']) ?
            $this->options['formats'][$field]['render']['bypass_eval_before'] : FALSE,
          '#states' => [
            'visible' => [
              ":input[name=\"row_options[formats][$field][render][use_php]\"]" => ['checked' => TRUE],
            ],
          ],
        ];

        $form['formats'][$field]['render']['eval_after'] = [
          '#type' => 'textarea',
          '#title' => $this->t('PHP Code After Output'),
          '#description' =>
            t('Please avoid direct PHP here, this feature is deprecated in favour of the custom post render hook'),
          '#default_value' => $this->options['formats'][$field]['render']['eval_after'] ?? '',
          '#states' => [
            'visible' => [
              ":input[name=\"row_options[formats][$field][render][use_php]\"]" => ['checked' => TRUE],
            ],
          ],
        ];
        $form['formats'][$field]['render']['bypass_eval_after'] = [
          '#type' => 'checkbox',
          '#title' => $this->t('Use the PHP eval function instead php_eval.'),
          '#description' => $this->t("WARNING: If you don't know the risk of using eval leave unckecked."),
          '#default_value' => !empty($this->options['formats'][$field]['render']['bypass_eval_after']) ?
            $this->options['formats'][$field]['render']['bypass_eval_after'] : FALSE,
          '#states' => [
            'visible' => [
              ":input[name=\"row_options[formats][$field][render][use_php]\"]" => ['checked' => TRUE],
            ],
          ],
        ];
      }

    }
  }

}
