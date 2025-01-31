<?php

namespace Drupal\fpdi_print\Form;

use Drupal\Core\Form\ConfigFormBase;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Form\FormStateInterface;

/**
 * Defines a form that configures FPDI Print settings.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * The entity config storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $storage;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'fpdi_print_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'fpdi_print.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, Request $request = NULL) {
    $config = $this->config('fpdi_print.settings');
    // Global settings.
    $form['default_css'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Path print CSS'),
      '#description' => $this->t('Provides css styles for print.'),
      '#default_value' => $config->get('default_css'),
    ];
    $form['force_download'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Force Download'),
      '#description' => $this->t('This option will attempt to force the browser to download the Print with a filename from the node title.'),
      '#default_value' => $config->get('force_download'),
    ];

    $page_formats = array_combine(array_keys(\TCPDF_STATIC::$page_formats), array_keys(\TCPDF_STATIC::$page_formats));
    $form['page_format'] = [
      '#title' => $this->t('Paper Size'),
      '#type' => 'select',
      '#options' => $page_formats,
      '#default_value' => $config->get('page_format'),
      '#description' => $this->t('The page size to print the PDF to.'),
    ];
    $form['orientation'] = [
      '#title' => $this->t('Paper Orientation'),
      '#type' => 'select',
      '#options' => [
        'P' => $this->t('Portrait'),
        'L' => $this->t('Landscape'),
      ],
      '#default_value' => $config->get('orientation'),
      '#description' => $this->t('The paper orientation one of Landscape or Portrait'),
    ];
    $form['footer_height'] = [
      '#type' => 'number',
      '#title' => $this->t('Page footer height'),
      '#description' => $this->t('Set footer height if too many lines'),
      '#default_value' => $config->get('footer_height'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('fpdi_print.settings');

    // Save the global settings.
    $values = $form_state->getValues();
    $config->set('default_css', $values['default_css'])
      ->set('force_download', $values['force_download'])
      ->set('page_format', $values['page_format'])
      ->set('orientation', $values['orientation'])
      ->set('footer_height', $values['footer_height'])
      ->save();

    $this->messenger()->addStatus($this->t('Configuration saved.'));
  }

}
