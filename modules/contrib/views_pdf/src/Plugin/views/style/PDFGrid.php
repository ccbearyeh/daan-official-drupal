<?php
declare(strict_types=1);

namespace Drupal\views_pdf\Plugin\views\style;

use Drupal\Core\Form\FormStateInterface;

/**
 * Style plugin to render a PDF Table display style.
 *
 * @ingroup views_style_plugins
 *
 * @ViewsStyle(
 *   id = "pdf_grid",
 *   title = @Translation("PDF Unformatted Grid"),
 *   help = @Translation("Display the view unformatted in a grid layout."),
 *   register_theme = FALSE,
 *   display_types={"pdf"}
 * )
 */
class PDFGrid extends PDFBaseStyle {

  /**
   * {@inheritdoc}
   */
  protected $usesRowPlugin = TRUE;

  /**
   * {@inheritDoc}
   */
  public function getStyle(): string {
    return 'pdf_grid';
  }

  /**
   * {@inheritDoc}
   */
  public function renderBuild(): array {
    $options = $this->options;

    $cols = $options['columns'];
    $colspace = $options['column_space'];
    $rows = $options['rows'];
    $rowspace = $options['row_space'];

    // Need to add the first page here to get dimensions.
    $this->view->pdf->addPage();
    $fullpage = FALSE;
    // Calculate grid parameters - cell size and offsets.
    $pgdim = $this->view->pdf->getPageDimensions();
    $cell_width = ($pgdim['wk'] - $pgdim['lm'] - $pgdim['rm'] - $colspace * ($cols - 1)) / $cols;
    $cell_height = ($pgdim['hk'] - $pgdim['tm'] - $pgdim['bm'] - $rowspace * ($rows - 1)) / $rows;

    $this->view->rowPlugin->options['grid'] = [
      'w' => $cell_width,
      'h' => $cell_height,
    ];

    // Set up indirect variables so as to iterate row-wise or column-wise.
    if ($options['col_wise']) {
      $first = $rows;
      $second = $cols;
      $first_idx = 'rowidx';
      $second_idx = 'colidx';
    }
    else {
      $first = $cols;
      $second = $rows;
      $first_idx = 'colidx';
      $second_idx = 'rowidx';
    }

    $colidx = $rowidx = 0;

    foreach ($this->view->result as $row_index => $row) {
      $this->view->row_index = $row_index;
      if ($fullpage) {
        $this->view->pdf->addPage();
        $fullpage = FALSE;
      }
      // Calculate co-ordinates of top left corner of current grid cell.
      $this->view->rowPlugin->options['grid']['x'] = $colidx * ($cell_width + $colspace);
      $this->view->rowPlugin->options['grid']['y'] = $rowidx * ($cell_height + $rowspace);
      $this->view->rowPlugin->options['grid']['new_cell'] = TRUE;

      $this->view->rowPlugin->render($row);

      // Use variable-variables to run row or column wise.
      if (++$$first_idx === $first) {
        $$first_idx = 0;
        if (++$$second_idx === $second) {
          $$second_idx = 0;
          $fullpage = TRUE;
        }
      }
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

  /**
   * {@inheritDoc}
   */
  protected function defineOptions(): array {
    $options = parent::defineOptions();

    $this->definition['uses grouping'] = FALSE;

    $options['columns'] = ['default' => 2];
    $options['column_space'] = ['default' => 0];
    $options['rows'] = ['default' => 8];
    $options['row_space'] = ['default' => 0];
    $options['col_wise'] = ['default' => 0];

    return $options;
  }

  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    $attached = [
      'css' => [\Drupal::service('extension.list.module')->getPath('views_pdf') . '/theme/admin.css'],
    ];
    $form['#attached'] = $attached;

    $form['columns'] = [
      '#type' => 'number',
      '#min' => 1,
      '#max' => 9999,
      '#title' => $this->t('Grid columns'),
      '#default_value' => $this->options['columns'] ?? 1,
    ];
    $form['column_space'] = [
      '#type' => 'number',
      '#min' => 1,
      '#max' => 9999,
      '#title' => $this->t('Column spacing'),
      '#default_value' => $this->options['column_space'] ?? 1,
    ];
    $form['rows'] = [
      '#type' => 'number',
      '#min' => 1,
      '#max' => 9999,
      '#title' => $this->t('Grid rows'),
      '#default_value' => $this->options['rows'] ?? 1,
    ];
    $form['row_space'] = [
      '#type' => 'number',
      '#min' => 0,
      '#max' => 9999,
      '#title' => $this->t('Row spacing'),
      '#default_value' => $this->options['row_space'] ?? 0,
    ];
    $form['col_wise'] = [
      '#type' => 'radios',
      '#title' => $this->t('Layout order'),
      '#options' => [
        0 => $this->t('Row-first'),
        1 => $this->t('Column-first')
      ],
      '#default_value' => $this->options['col_wise'] ?? 1,
    ];

  }

}
