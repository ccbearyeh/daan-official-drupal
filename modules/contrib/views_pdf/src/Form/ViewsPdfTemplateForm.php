<?php
declare(strict_types=1);

namespace Drupal\views_pdf\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\file\Entity\File;

/**
 * Views PDF Template form.
 *
 * @property \Drupal\views_pdf\ViewsPdfTemplateInterface $entity
 */
class ViewsPdfTemplateForm extends EntityForm {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {

    $form = parent::form($form, $form_state);

    $form['#attributes'] = [
      'enctype' => 'multipart/form-data',
    ];

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $this->entity->label(),
      '#description' => $this->t('Label for the views pdf template.'),
      '#required' => TRUE,
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $this->entity->id(),
      '#machine_name' => [
        'exists' => '\Drupal\views_pdf\Entity\ViewsPdfTemplate::load',
      ],
      '#disabled' => !$this->entity->isNew(),
    ];

    $status = $this->entity->id();

    $form['status'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enabled'),
      '#default_value' => !$status || $this->entity->status(),
    ];

    $form['description'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Description'),
      '#default_value' => $this->entity->get('description'),
      '#description' => $this->t('Description of the Views PDF Template.'),
    ];

    $form['template'] = [
      '#type' => 'managed_file',
      '#name' => 'views_pdf_template',
      '#title' => $this->t('Template file'),
      '#default_value' => $this->entity->get('template'),
      '#description' => $this->t('Select a file as template. Supported file pdf'),
      '#upload_validators' => [
        'file_validate_extensions' => ['pdf']
      ],
      '#upload_location' => \Drupal::config('views_pdf.settings')->get('views_pdf_template_path'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $result = parent::save($form, $form_state);
    $file = \Drupal::entityTypeManager()->getStorage('file')->load($this->entity->get('template')[0]);
    $file->setPermanent();
    $file->save();
    $message_args = ['%label' => $this->entity->label()];
    $message = $result === SAVED_NEW
      ? $this->t('Created new views pdf template %label.', $message_args)
      : $this->t('Updated views pdf template %label.', $message_args);
    $this->messenger()->addStatus($message);
    $form_state->setRedirectUrl($this->entity->toUrl('collection'));
    return $result;
  }

}
