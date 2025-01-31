<?php

namespace Drupal\fpdi_print\Plugin\views\area;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\views\Plugin\views\area\TokenizeAreaPluginBase;

/**
 * Views area handler for a pdf button.
 *
 * @ingroup views_area_handlers
 *
 * @ViewsArea("fpdi_print_views_link")
 */
class FpdiPrintViewsLink extends TokenizeAreaPluginBase {

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    $form['position_text'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Position configuration'),
      '#description' => $this->t('YAML array format, for example:') . '<br/>' . "
      <pre>- x: 10
  y: 10
  text: \"[site:name]\"
- x: 20
  y: 10
  text: \"{{ field_name }}\"</pre>
  or Form filling
  <pre>- form: \"address\"
  text:  \"[site:name]\"
- form: \"name\"
  text: \"{{ field_name }}
</pre>",
      '#default_value' => $this->options['position_text'],
    ];

    $form['print_logo'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Use logo, slogan for the header'),
      '#default_value' => $this->options['print_logo'],
    ];

    $form['link_text'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Link text'),
      '#required' => TRUE,
      '#default_value' => $this->options['link_text'],
    ];
    $path = \Drupal::service('file_system')
      ->realpath(\Drupal::config('system.file')->get('default_scheme') . "://");
    $form['file_pdf'] = [
      '#type' => 'textfield',
      '#title' => $this->t('PDF document (version 1.4) template path'),
      '#description' => $this->t('It does not work with version 1.5 later, <a href="https://docupub.com/pdfconvert/">convert to acrobat 5 (PDF 1.4)</a>. <br/>Your system path: %path', ['%path' => $path]),
      '#default_value' => $this->options['file_pdf'],
    ];
    $form['file_pdf']['#description'] = $this->t('See more for <a href="http://www.fpdf.org/en/script/script93.php">PDF Form Filling</a>');

    $form['file_pdf_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Save PDF to name'),
      '#description' => $this->t('Leave blank to view the pdf with the browser. If you set the file name, it will download the pdf, If you set path + file name, it will be saved in the path and downloaded. <br/> The token can be used for filename'),
      '#default_value' => $this->options['file_pdf_name'],
    ];

    $displays = $this->view->displayHandlers->getConfiguration();
    $display_options = [];
    foreach ($displays as $display_id => $display_info) {
      $display_options[$display_id] = $display_info['display_title'];
    }
    $form['display_id'] = [
      '#type' => 'select',
      '#title' => $this->t('View Display'),
      '#options' => $display_options,
      "#empty_option" => $this->t('- Select -'),
      '#default_value' => $this->options['display_id'],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function render($empty = FALSE) {
    $route_params = [
      'view_name' => $this->view->storage->id(),
      'display_id' => $this->options['display_id'],
      'option_id' => $this->areaType . '-' . $this->options['id'],
    ];
    return [
      '#type' => 'link',
      '#title' => $this->options['link_text'],
      '#attributes' => ['class' => ['print-file-pdf']],
      '#url' => Url::fromRoute('fpdi_print.view', $route_params, [
        'query' => $this->view->getExposedInput() + ['view_args' => $this->view->args],
      ]),
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['position_text'] = ['default' => ''];
    $options['file_pdf'] = ['default' => ''];
    $options['file_pdf_name'] = ['default' => ''];
    $options['print_logo'] = ['default' => FALSE];
    $options['link_text'] = ['default' => $this->t('Download PDF')];
    $options['display_id'] = ['default' => $this->view->current_display];
    return $options;
  }

}
