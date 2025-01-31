<?php
declare(strict_types=1);

namespace Drupal\views_pdf\Form;

use Drupal\Core\Url;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\views_pdf\PdfLibrary\FPDI;
use Drupal\views_pdf\Entity\ViewsPdfTemplate;

class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'views_pdf_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['views_pdf.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $settings = $this->config('views_pdf.settings');


    $fonts = FPDI::getAvailableFontsCleanList();
    $font_styles = [
      'b' => $this->t('Bold'),
      'i' => $this->t('Italic'),
      'u' => $this->t('Underline'),
      'd' => $this->t('Line through'),
      'o' => $this->t('Overline'),
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
    // TODO: Build new Entity Hyphenate.
    $hyphenate = array_merge($hyphenate, []);

    // PDF Page Views
    $form['pdfBase'] = [
      '#type' => 'details',
      '#title' => $this->t('PDF Page settings'),
    ];
    $form['pdfBase']['default_page_format'] = [
      '#type' => 'select',
      '#title' => $this->t('Default Page Format'),
      '#required' => TRUE,
      '#options' => FPDI::pdfGetPageFormats(),
      '#description' => $this->t('This is the default page format. If you specifiy a different format in the template section, this settings will be override.'),
      '#default_value' => $settings->get('default_page_format'),
    ];
    $form['pdfBase']['default_page_format_custom'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Custom Page Format'),
      '#description' => $this->t('Here you can specifiy a custom page format. The schema is "[width]x[height]".'),
      '#default_value' => $settings->get('default_page_format_custom'),
    ];
    $form['pdfBase']['default_page_orientation'] = [
      '#type' => 'radios',
      '#title' => $this->t('Default Page Orientation'),
      '#required' => TRUE,
      '#options' => ['P' => $this->t('Portrait'), 'L' => $this->t('Landscape')],
      '#description' => $this->t('This is the default page orientation.'),
      '#default_value' => $settings->get('default_page_orientation'),
    ];
    $form['pdfBase']['unit'] = [
      '#type' => 'select',
      '#title' => $this->t('Unit'),
      '#required' => TRUE,
      '#options' => [
        'mm' => $this->t('mm: Millimeter'),
        'pt' => $this->t('pt: Point'),
        'cm' => $this->t('cm: Centimeter'),
        'in' => $this->t('in: Inch'),
      ],
      '#description' => $this->t('This is the unit for the entered unit data. If you change this option all defined units were changed, but not converted.'),
      '#default_value' => $settings->get('unit'),
    ];
    $form['pdfBase']['margin_left'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Margin: Left'),
      '#required' => TRUE,
      '#default_value' => $settings->get('margin_left'),
    ];
    $form['pdfBase']['margin_right'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Margin: Right'),
      '#required' => TRUE,
      '#default_value' => $settings->get('margin_right'),
    ];
    $form['pdfBase']['margin_top'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Margin: Top'),
      '#required' => TRUE,
      '#default_value' => $settings->get('margin_top'),
    ];
    $form['pdfBase']['margin_bottom'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Margin: Bottom'),
      '#required' => TRUE,
      '#default_value' => $settings->get('margin_bottom'),
    ];
    $form['pdfBase']['notes']['#markup'] = $this->t('PDF Default Font Settings');
    $form['pdfBase']['description'] = [
      '#prefix' => '<div class="description form-item">',
      '#suffix' => '</div>',
      '#value' => $this->t('Here you specify a the default font settings for the document.'),
    ];
    $form['pdfBase']['default_font_size'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Font Size'),
      '#size' => 10,
      '#default_value' => $settings->get('default_font_size'),
    ];
    $form['pdfBase']['default_font_family'] = [
      '#type' => 'select',
      '#title' => $this->t('Font Family'),
      '#options' => $fonts,
      '#size' => 5,
      '#default_value' => $settings->get('default_font_family'),
    ];
    $form['pdfBase']['default_font_style'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Font Style'),
      '#options' => $font_styles,
      '#default_value' => $settings->get('default_font_style') ?? [],
    ];
    $form['pdfBase']['right_to_left'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Set text to be Right to Left'),
      '#description' => $this->t('For languages such arabic japanese with the rule right to left.'),
      '#default_value' => $settings->get('right_to_left'),
    ];
    $form['pdfBase']['default_text_align'] = [
      '#type' => 'radios',
      '#title' => $this->t('Text Alignment'),
      '#options' => $align,
      '#default_value' => $settings->get('default_text_align'),
    ];
    $form['pdfBase']['default_text_hyphenate'] = [
      '#type' => 'select',
      '#title' => $this->t('Text Hyphenation'),
      '#options' => $hyphenate,
      '#description' => $this->t('If you want to use hyphenation, then you need to download from <a href="@url">ctan.org</a> your needed pattern set. Then upload it to the dir "hyphenate_patterns" in the TCPDF lib directory. Perhaps you need to create the dir first. If you select the automated detection, then we try to get the language of the current node and select an appropriate hyphenation pattern.', ['@url' => 'http://www.ctan.org/tex-archive/language/hyph-utf8/tex/generic/hyph-utf8/patterns/tex']),
      '#default_value' => $settings->get('default_text_hyphenate'),
    ];

    $form['pdfBase']['default_font_color'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Text Color'),
      '#description' => $this->t('If a value is entered without a comma, it will be interpreted as a hexadecimal RGB color. Normal RGB can be used by separating the components by a comma. e.g 255,255,255 for white. A CMYK color can be entered in the same way as RGB. e.g. 0,100,0,0 for magenta.'),
      '#size' => 20,
      '#default_value' => $settings->get('default_font_color'),
    ];

    // Header section.
    $form['header'] = [
      '#type' => 'details',
      '#title' => $this->t('PDF Header settings'),
    ];
    $form['header']['header_margin'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Top margin'),
      '#size' => 10,
      '#default_value' => $settings->get('header_margin'),
    ];
    $form['header']['header_font_size'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Font Size'),
      '#size' => 10,
      '#default_value' => $settings->get('header_font_size'),
    ];
    $form['header']['header_font_family'] = [
      '#type' => 'select',
      '#title' => $this->t('Font Family'),
      '#options' => $fonts,
      '#size' => 5,
      '#default_value' => $settings->get('header_font_family'),
    ];
    $form['header']['header_font_style'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Font Style'),
      '#options' => $font_styles,
      '#default_value' => $settings->get('header_font_style') ?? [],
    ];
    $form['header']['header_text_align'] = [
      '#type' => 'radios',
      '#title' => $this->t('Text Alignment'),
      '#options' => $align,
      '#default_value' => $settings->get('header_text_align'),
    ];
    $form['header']['header_font_color'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Text Color'),
      '#description' => $this->t('If a value is entered without a comma, it will be interpreted as a hexadecimal RGB color. Normal RGB can be used by separating the components by a comma. e.g 255,255,255 for white. A CMYK color can be entered in the same way as RGB. e.g. 0,100,0,0 for magenta.'),
      '#size' => 20,
      '#default_value' => $settings->get('header_font_color'),
    ];

    $form['footer'] = [
      '#type' => 'details',
      '#title' => $this->t('Footer options'),
    ];
    $form['footer']['footer_spacing'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Bottom spacing'),
      '#size' => 10,
      '#default_value' => $settings->get('footer_spacing'),
    ];
    $form['footer']['footer_font_size'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Font Size'),
      '#size' => 10,
      '#default_value' => $settings->get('footer_font_size'),
    ];
    $form['footer']['footer_font_family'] = [
      '#type' => 'select',
      '#title' => $this->t('Font Family'),
      '#options' => $fonts,
      '#size' => 5,
      '#default_value' => $settings->get('footer_font_family'),
    ];
    $form['footer']['footer_font_style'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Font Style'),
      '#options' => $font_styles,
      '#default_value' => $settings->get('footer_font_style') ?? [],
    ];
    $form['footer']['footer_text_align'] = [
      '#type' => 'radios',
      '#title' => $this->t('Text Alignment'),
      '#options' => $align,
      '#default_value' => $settings->get('footer_text_align'),
    ];
    $form['footer']['footer_font_color'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Text Color'),
      '#description' => $this->t('If a value is entered without a comma, it will be interpreted as a hexadecimal RGB color. Normal RGB can be used by separating the components by a comma. e.g 255,255,255 for white. A CMYK color can be entered in the same way as RGB. e.g. 0,100,0,0 for magenta.'),
      '#size' => 20,
      '#default_value' => $settings->get('footer_font_color'),
    ];

    $form['pdfTemplates'] = [
      '#type' => 'details',
      '#title' => $this->t('PDF Templates'),
    ];

    $form['pdfTemplates']['leading'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Leading PDF Template'),
    ];
    $form['pdfTemplates']['leading']['leading_template'] = [
      '#type' => 'entity_autocomplete',
      '#target_type' => 'views_pdf_template',
      '#selection_handler' => 'default',
      '#description' => $this->t('Here you specify a PDF file to be printed before the main content.'),
      '#default_value' => $settings->get('leading_template') ? ViewsPdfTemplate::load($settings->get('leading_template')) : '',
    ];
    $form['pdfTemplates']['leading']['leading_header'] = [
      '#type' => 'checkbox',
      '#title' => 'Print page headers on leading template',
      '#default_value' => $settings->get('leading_header'),
    ];
    $form['pdfTemplates']['leading']['leading_footer'] = [
      '#type' => 'checkbox',
      '#title' => 'Print page footers on leading template',
      '#default_value' => $settings->get('leading_footer'),
    ];

    $form['pdfTemplates']['background'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Background PDF Template'),
    ];
    $form['pdfTemplates']['background']['template'] = [
      '#type' => 'entity_autocomplete',
      '#target_type' => 'views_pdf_template',
      '#selection_handler' => 'default',
      '#description' => $this->t('Here you specify a PDF file on which the content is printed. The first page of this document is used for the first page, in the target document. The second page is used for the second page in the target document and so on. If the target document has more that this template file, the last page of the template will be repeated. The leading document has no effect on the order of the pages.'),
      '#default_value' => $settings->get('template') ? ViewsPdfTemplate::load($settings->get('template')) : '',
    ];

    $form['pdfTemplates']['succeed'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Trailing PDF Template'),
    ];
    $form['pdfTemplates']['succeed']['succeed_template'] = [
      '#type' => 'entity_autocomplete',
      '#target_type' => 'views_pdf_template',
      '#selection_handler' => 'default',
      '#description' => $this->t('Here you specify a PDF file to be printed after the main content.'),
      '#default_value' => $settings->get('succeed_template') ? ViewsPdfTemplate::load($settings->get('succeed_template')) : '',
    ];
    $form['pdfTemplates']['succeed']['succeed_header'] = [
      '#type' => 'checkbox',
      '#title' => 'Print page headers on trailing template',
      '#default_value' => $settings->get('succeed_header'),
    ];
    $form['pdfTemplates']['succeed']['succeed_footer'] = [
      '#type' => 'checkbox',
      '#title' => 'Print page footers on trailing template',
      '#default_value' => $settings->get('succeed_footer'),
    ];

    $form['notes']['#markup'] =
      $this->t('To manage template files and upload new ones, go to the <a href=":link">PDF Templates</a> tab on the main Views page.', [':link' => Url::fromRoute('entity.views_pdf_template.collection')->toString()]);


    return parent::buildForm($form, $form_state);
  }

  public function validateForm(array &$form, FormStateInterface $form_state) {

  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {

    $this->config('views_pdf.settings')
      ->set('page_details_open', $form_state->getValue('page_details_open'))
      ->set('default_page_format', $form_state->getValue('default_page_format'))
      ->set('default_page_format_custom', $form_state->getValue('default_page_format_custom'))
      ->set('default_page_orientation', $form_state->getValue('default_page_orientation'))
      ->set('unit', $form_state->getValue('unit'))
      ->set('margin_left', $form_state->getValue('margin_left'))
      ->set('margin_right', $form_state->getValue('margin_right'))
      ->set('margin_top', $form_state->getValue('margin_top'))
      ->set('margin_bottom', $form_state->getValue('margin_bottom'))
      ->set('leading_template', $form_state->getValue('leading_template'))
      ->set('template', $form_state->getValue('template'))
      ->set('succeed_template', $form_state->getValue('succeed_template'))
      ->set('default_font_family', $form_state->getValue('default_font_family'))
      ->set('right_to_left', $form_state->getValue('right_to_left'))
      ->set('default_font_style', $form_state->getValue('default_font_style'))
      ->set('default_font_size', $form_state->getValue('default_font_size'))
      ->set('default_text_align', $form_state->getValue('default_text_align'))
      ->set('default_font_color', $form_state->getValue('default_font_color'))
      ->set('default_text_hyphenate', $form_state->getValue('default_text_hyphenate'))
      ->set('header_margin', $form_state->getValue('header_margin'))
      ->set('header_font_family', $form_state->getValue('header_font_family'))
      ->set('header_font_style', $form_state->getValue('header_font_style'))
      ->set('header_font_size', $form_state->getValue('header_font_size'))
      ->set('header_text_align', $form_state->getValue('header_text_align'))
      ->set('header_font_color', $form_state->getValue('header_font_color'))
      ->set('footer_spacing', $form_state->getValue('footer_spacing'))
      ->set('footer_font_family', $form_state->getValue('footer_font_family'))
      ->set('footer_font_style', $form_state->getValue('footer_font_style'))
      ->set('footer_font_size', $form_state->getValue('footer_font_size'))
      ->set('footer_text_align', $form_state->getValue('footer_text_align'))
      ->set('footer_font_color', $form_state->getValue('footer_font_color'))
      ->set('leading_template', $form_state->getValue('leading_template'))
      ->set('leading_header', $form_state->getValue('leading_header'))
      ->set('leading_footer', $form_state->getValue('leading_footer'))
      ->set('template', $form_state->getValue('template'))
      ->set('succeed_template', $form_state->getValue('succeed_template'))
      ->set('succeed_header', $form_state->getValue('succeed_header'))
      ->set('succeed_footer', $form_state->getValue('succeed_footer'))
      ->save();

    parent::submitForm($form, $form_state);
  }
}
