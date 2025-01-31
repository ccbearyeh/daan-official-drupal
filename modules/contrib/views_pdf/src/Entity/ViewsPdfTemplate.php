<?php
declare(strict_types=1);

namespace Drupal\views_pdf\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\views_pdf\ViewsPdfTemplateInterface;

/**
 * Defines the views pdf template entity type.
 *
 * @ConfigEntityType(
 *   id = "views_pdf_template",
 *   label = @Translation("Views PDF Template"),
 *   label_collection = @Translation("Views PDF Templates"),
 *   label_singular = @Translation("views pdf template"),
 *   label_plural = @Translation("views pdf templates"),
 *   label_count = @PluralTranslation(
 *     singular = "@count views pdf template",
 *     plural = "@count views pdf templates",
 *   ),
 *   handlers = {
 *     "list_builder" = "Drupal\views_pdf\ViewsPdfTemplateListBuilder",
 *     "form" = {
 *       "add" = "Drupal\views_pdf\Form\ViewsPdfTemplateForm",
 *       "edit" = "Drupal\views_pdf\Form\ViewsPdfTemplateForm",
 *       "delete" = "Drupal\Core\Entity\EntityDeleteForm"
 *     }
 *   },
 *   config_prefix = "views_pdf_template",
 *   admin_permission = "administer views_pdf_template",
 *   links = {
 *     "collection" = "/admin/structure/views-pdf-template",
 *     "add-form" = "/admin/structure/views-pdf-template/add",
 *     "edit-form" = "/admin/structure/views-pdf-template/{views_pdf_template}",
 *     "delete-form" = "/admin/structure/views-pdf-template/{views_pdf_template}/delete"
 *   },
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid"
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "description",
 *     "template"
 *   }
 * )
 */
class ViewsPdfTemplate extends ConfigEntityBase implements ViewsPdfTemplateInterface {

  /**
   * The views pdf template ID.
   *
   * @var string
   */
  protected $id;

  /**
   * The views pdf template label.
   *
   * @var string
   */
  protected $label;

  /**
   * The views pdf template status.
   *
   * @var bool
   */
  protected $status;

  /**
   * The views_pdf_template description.
   *
   * @var string
   */
  protected $description;

  /**
   * The Template path.
   *
   * @var string
   */
  protected $template;

}
