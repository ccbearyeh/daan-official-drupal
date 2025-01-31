<?php
declare(strict_types=1);

namespace Drupal\views_pdf\Plugin\views\style;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views_pdf\PdfLibrary\FPDI;

/**
 * Style plugin to render a PDF Table display style.
 *
 * @ingroup views_style_plugins
 *
 * @ViewsStyle(
 *   id = "pdf_table",
 *   title = @Translation("PDF Table"),
 *   help = @Translation("Display the view as a table."),
 *   register_theme = FALSE,
 *   display_types={"pdf"}
 * )
 */
class PDFTable extends PDFBaseStyle {

  /**
   * {@inheritDoc}
   */
  public function getStyle(): string {
    return 'pdf_table';
  }

  /**
   * {@inheritDoc}
   */
  public function renderBuild(): array {
    $this->view->numberOfRecords = count($this->view->result);
    $this->view->pdf->drawTable($this->view, $this->options);

    return  [
      '#view' => $this->view,
    ];
  }

  /**
   * {@inheritDoc}
   */
  public function render(): array {
    $render = match($this->request->get('_route')) {
      'entity.view.preview_form' => $this->previewRender(),
      default => $this->renderBuild(),
    };

    return $render;
  }

  /**
   * {@inheritDoc}
   */
  public function defineOptions(): array {
    $options = parent::defineOptions();

    $this->definition['uses grouping'] = false;

    $options['info'] = ['default' => []];
    $options['position'] = ['default' => []];

    return $options;
  }

  /**
   * {@inheritDoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    $handlers = $this->displayHandler->getHandlers('field');

    if (empty($handlers)) {
      $form['error_markup'] = [
        '#markup' => '<div class="error messages">' . $this->t('You need at least one field before you can configure your table settings') . '</div>',
      ];
      return;
    }
    $attached = [
      'css' => [\Drupal::service('extension.list.module')->getPath('views_pdf') . '/theme/admin.css'],
    ];



    $form['#theme'] = 'views_pdf_plugin_style_table';
    $form['#attached'] = $attached;

    $columns = ['_default_' => ''];
    $columns += $this->displayHandler->getFieldLabels();
    $fields = $this->displayHandler->getOption('fields');

    $fonts = array_merge(['default' => $this->t('-- Default --')], FPDI::getAvailableFontsCleanList());

    $font_styles = [
      'b' => $this->t('Bold'),
      'i' => $this->t('Italic'),
      'u' => $this->t('Underline'),
      'd' => $this->t('Line through'),
      'o' => $this->t('Overline')
    ];
    $align = [
      'L' => $this->t('Left'),
      'C' => $this->t('Center'),
      'R' => $this->t('Right'),
      'J' => $this->t('Justify'),
    ];

    $hyphenate = [
      'none' => $this->t('None'),
      'auto' => $this->t('Detect automatically'),
    ];

    // TODO: Entity Hyphenate.
    $hyphenate = array_merge($hyphenate, []);

    foreach ($columns as $field => $column) {

      // Skip excluded fields and the page-break field.
      if (!empty($fields[$field]['exclude']) || $field == 'page_break') {
        continue;
      }

      // markup for the field name
      $form['info'][$field]['name'] = [
        '#markup' => $column,
      ];

      foreach(['header_style', 'body_style'] as $style) {

        $info_text = empty($this->options['info'][$field][$style]['text']) ?
          NULL : $this->options['info'][$field][$style]['text'];

        $form['info'][$field][$style]['text'] = [
          '#type' => 'fieldset',
          '#title' => $this->t('Text Settings'),
          '#collapsed' => TRUE,
          '#collapsible' => TRUE,
        ];

        $form['info'][$field][$style]['text']['font_size'] = [
          '#type' => 'textfield',
          '#size' => 10,
          '#title' => $this->t('Font Size'),
          '#default_value' => empty($info_text['font_size']) ? '' : $info_text['font_size'],
        ];

        $form['info'][$field][$style]['text']['font_family'] = [
          '#type' => 'select',
          '#title' => $this->t('Font Family'),
          '#required' => TRUE,
          '#options' => $fonts,
          '#size' => 5,
          '#default_value' => empty($info_text['font_family']) ? 'default' : $info_text['font_family'],
        ];

        $form['info'][$field][$style]['text']['font_style'] = [
          '#type' => 'checkboxes',
          '#title' => $this->t('Font Style'),
          '#options' => $font_styles,
          '#size' => 10,
          '#default_value' => empty($info_text['font_style']) ?
            $this->displayHandler->getOption('default_font_style') : $info_text['font_style'],
        ];
        $form['info'][$field][$style]['text']['align'] = [
          '#type' => 'radios',
          '#title' => $this->t('Alignment'),
          '#options' => $align,
          '#default_value' => empty($info_text['align']) ?
            $this->displayHandler->getOption('default_text_align') : $info_text['align'],
        ];

        $form['info'][$field][$style]['text']['hyphenate'] = [
          '#type' => 'select',
          '#title' => $this->t('Text Hyphenation'),
          '#options' => $hyphenate,
          '#description' => $this->t('upload patterns from <a href=":url">ctan.org</a> to <br />sites/libraries/tcpdf/hyphenate_patterns', [':url' => 'http://www.ctan.org/tex-archive/language/hyph-utf8/tex/generic/hyph-utf8/patterns/tex']),
          '#default_value' => empty($info_text['hyphenate']) ?
            $this->displayHandler->getOption('default_text_hyphenate') : $info_text['hyphenate'],
        ];

        $form['info'][$field][$style]['text']['color'] = [
          '#type' => 'textfield',
          '#title' => $this->t('Text Color'),
          '#description' => $this->t('Enter Any format: <br />000000 (HexRGB) - 000,000,000 (RGB) - 000,000,000,000 (CMYK)'),
          '#size' => 10,
          '#default_value' => empty($info_text['color']) ?
            $this->displayHandler->getOption('default_text_color') : $info_text['color'],
        ];

        $form['info'][$field][$style]['text']['vpad'] = [
          '#type' => 'textfield',
          '#title' => $this->t('Vertical padding'),
          '#description' => $this->t('Padding space to apply above and below text in each row (within borders if used)'),
          '#size' => 6,
          '#default_value' => empty($info_text['vpad']) ? '' : $info_text['vpad'],
        ];

        $form['info'][$field][$style]['text']['border'] = [
          '#type' => 'textfield',
          '#title' => $this->t('Border'),
          '#description' => $this->t('1 = full border, or any combination of letters L, R, T, B'),
          '#size' => 6,
          '#default_value' => empty($info_text['border']) ? '' : $info_text['border'],
        ];

        // TODO: Wrap another way more safe to execute code or not.
        if (defined('VIEWS_PDF_PHP') && $field != '_default_' && user_access('use PHP for settings')) {

          $info_render = empty($this->options['info'][$field][$style]['render']) ?
            NULL : $this->options['info'][$field][$style]['render'];

          $form['info'][$field][$style]['render'] = [
            '#type' => 'fieldset',
            '#title' => $this->t('Render Settings'),
            '#collapsed' => TRUE,
            '#collapsible' => TRUE,
          ];
          $form['info'][$field][$style]['render']['eval_before'] = [
            '#type' => 'textarea',
            '#title' => $this->t('PHP Code Before Output'),
            '#description' => $this->t('Please avoid direct PHP here, this feature is deprecated in favour of hook_views_pdf_custom_layout()'),
            '#default_value' => empty($info_render['eval_before']) ? '' : $info_render['eval_before'],
          ];
          $form['info'][$field][$style]['render']['bypass_eval_before'] = [
            '#type' => 'checkbox',
            '#title' => $this->t('Use the PHP eval function instead php_eval.'),
            '#description' => $this->t("WARNING: If you don't know the risk of using eval leave unckecked."),
            '#default_value' => empty($info_render['bypass_eval_before']) ? FALSE : $info_render['bypass_eval_before'],
          ];

          $form['info'][$field][$style]['render']['eval_after'] = [
            '#type' => 'textarea',
            '#title' => $this->t('PHP Code After Output'),
            '#description' => $this->t('Please avoid direct PHP here, this feature is deprecated in favour of hook_views_pdf_custom_post()'),
            '#default_value' => empty($info_render['eval_after']) ? '' : $info_render['eval_after'],
          ];
          $form['info'][$field][$style]['render']['bypass_eval_after'] = [
            '#type' => 'checkbox',
            '#title' => $this->t('Use the PHP eval function instead php_eval.'),
            '#description' => $this->t("WARNING: If you don't know the risk of using eval leave unckecked."),
            '#default_value' => empty($info_render['bypass_eval_after']) ? FALSE : $info_render['bypass_eval_after'],
          ];
        }
      }

      if ($field !== '_default_') {
        $form['info'][$field]['position']['width'] = [
          '#type' => 'textfield',
          '#size' => 10,
          '#default_value' => $this->options['info'][$field]['position']['width'] ?? '',
        ];

        $form['info'][$field]['empty']['hide_empty'] = [
          '#type' => 'checkbox',
          '#default_value' => $this->options['info'][$field]['empty']['hide_empty'] ?? 'FALSE',
        ];
      }
    }

    // Some general options
    $form['position'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Layout'),
      '#collapsed' => FALSE,
      '#collapsible' => TRUE,
    ];
    $form['position']['use_header'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Include column headings'),
      '#default_value' => $this->options['position']['use_header'] ?? 1,
    ];
    $form['position']['h'] = [
      '#type' => 'textfield',
      '#size' => 10,
      '#title' => $this->t('Header spacing'),
      '#description' => $this->t('Vertical space between column headings and table data'),
      '#default_value' => $this->options['position']['h'] ?? '',
      '#states' => [
        'invisible' => [
          ':input[name="style_options[position][use_header]"]' => ['checked' => FALSE],
        ],
      ],
    ];
    $form['position']['row_height'] = [
      '#type' => 'textfield',
      '#size' => 10,
      '#title' => $this->t('Row Height'),
      '#description' => $this->t('Height will always be enough for one line of text. A larger value will create space, and/or allow for multiple lines'),
      '#default_value' => $this->options['position']['row_height'] ?? '',
    ];

  }

  /**
   * {@inheritDoc}
   */
  public function submitOptionsForm(&$form, FormStateInterface $form_state) {
    $default_font_style = $this->displayHandler->getOption('default_font_style');

    foreach ($form_state->getValue('style_options')['info'] as $id => $field) {
      foreach(['header_style', 'body_style'] as $style) {
        // Reset to default, if the elements are equal to the default settings.
        if (count(array_diff($default_font_style, $field[$style]['text']['font_style'])) === 0 &&
          count(array_diff($field[$style]['text']['font_style'], $default_font_style)) === 0) {
          $form_state->setValue(['style_options','info', $id, $style, 'text', 'font_style'], null);
        }
        if ($field[$style]['text']['font_family'] === 'default') {
          $form_state->setValue(['style_options','info', $id, $style, 'text', 'font_family'], null);
        }

        if ($field[$style]['text']['align'] === $this->displayHandler->getOption('default_text_align')) {
          $form_state->setValue(['style_options','info', $id, $style, 'text', 'align'], null);
        }

        if ($field[$style]['text']['hyphenate'] === $this->displayHandler->getOption('default_text_hyphenate')) {
          $form_state->setValue(['style_options','info', $id, $style, 'text', 'hyphenate'], null);
        }

        // $form_state->setValue(['style_options','info', $id, $style, 'text', 'hyphenate'], null);
        // Strip all empty values.
        $form_state->setValue(['style_options','info', $id, $style], self::_array_filter_recursive($form_state->getValue('style_options')['info'][$id][$style]));
      }
    }

  }

  /**
   * A recursive version of PHP array_filter().
   *
   * @param $input
   *
   * @return array
   */
  protected static function _array_filter_recursive($input): array {
    foreach ($input as &$value) {
      if (is_array($value)) {
        $value = self::_array_filter_recursive($value);
      }
    }
    return array_filter($input);
  }



}
