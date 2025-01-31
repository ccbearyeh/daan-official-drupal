<?php
declare(strict_types=1);

namespace Drupal\views_pdf\Plugin\views\style;

use Drupal\Core\Annotation\Translation;
use Drupal\views\Annotation\ViewsStyle;
use function Symfony\Component\String\match;

/**
 * Style plugin to render a PDF Unformatted display style.
 *
   * @ingroup views_style_plugins
 *
 * @ViewsStyle(
 *   id = "pdf_unformatted",
 *   title = @Translation("PDF Unformatted"),
 *   help = @Translation("Outputs the view as a PDF Unformatted style."),
 *   register_theme = FALSE,
 *   display_types={"pdf"}
 * )
 */
class PDFUnformatted extends PDFBaseStyle {

  /**
   * {@inheritdoc}
   */
  protected $usesRowPlugin = TRUE;

  public function getStyle() : string {
    return 'pdf_unformatted';
  }

  /**
   * Block render phase on views preview.
   *
   * @return array
   */
  protected function previewRender(): array {
    $message = $this->t("PDF cannot be viewed as a live preview.");
    $this->messenger()->addWarning($message);

    return ['#markup' => $message];
  }

  /**
   * Work the render fields for unformatted PDF.
   *
   * @return array
   */
  protected function renderBuild(): array {

    foreach ($this->view->result as $row_index => $row) {
      $this->view->row_index = $row_index;
      $this->view->rowPlugin->render($row);
    }

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

}
