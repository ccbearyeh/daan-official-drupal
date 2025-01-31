<?php

namespace Drupal\fpdi_print;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use setasign\Fpdi\Tcpdf\Fpdi;

/**
 * {@inheritDoc}
 */
class Pdf extends Fpdi {

  use StringTranslationTrait;

  /**
   * Header.
   *
   * @var array
   */
  public $header = [];

  /**
   * Footer.
   *
   * @var array
   */
  public $footer = [];

  /**
   * Header Html.
   *
   * @var string
   */
  public $headerHTML = '';

  /**
   * Header margin top.
   *
   * @var int
   */
  public $headerMarginTop = 8;

  /**
   * Footer with html.
   *
   * @var string
   */
  public $footerHTML = '';

  /**
   * Footer height.
   *
   * @var int
   */
  public $footerHeight = 15;

  /**
   * Style.
   *
   * @var string
   */
  public $style = '';

  /**
   * Draw an imported PDF logo on every page.
   */
  public function Header() {
    if (!empty($this->header)) {
      $headerData = $this->header;
      $headerFont = $this->getHeaderFont();
      if (!empty($this->header['html'])) {
        $this->headerHTML .= $this->header['html'];
      }
      if (!empty($this->header['logo'])) {
        $path_parts = pathinfo($headerData['logo']);
        $imgtype = strtolower($path_parts['extension']);
        if (in_array($imgtype, ['eps', 'ai'])) {
          $this->ImageEps($headerData['logo'], '', '', $headerData['width']);
        }
        elseif ($imgtype == 'svg') {
          $this->ImageSVG($headerData['logo'], '', '', $headerData['width']);
        }
        else {
          $this->Image($headerData['logo'], '', $this->headerMarginTop, $headerData['width']);
        }
      }
      $cell_height = $this->getCellHeight($headerFont[2] / $this->k);
      // Set starting margin for text data cell.
      $header_x = $this->original_lMargin + ($headerData['width'] * 1.1);
      $cw = $this->w - $this->original_lMargin - $this->original_rMargin - ($headerData['width'] * 1.1);
      $this->SetTextColorArray($this->header_text_color);
      // Header title.
      $this->SetFont($headerFont[0], 'B', $headerFont[2] + 1);
      $this->SetXY($header_x, $this->headerMarginTop);
      if (!empty($headerData['title'])) {
        $this->Cell($headerData['width'], $cell_height, $headerData['title'], 0, 1, '', 0, '', 0);
      }
      // Header string.
      $this->SetFont($headerFont[0], $headerFont[1], $headerFont[2]);
      $this->SetX($header_x);
      $this->MultiCell($cw, $cell_height, $headerData['string'], 0, '', 0, 1, '', '', TRUE, 0, FALSE, TRUE, 0, 'T', FALSE);
    }
    if (!empty($this->headerHTML)) {
      if (!empty($this->style)) {
        $this->headerHTML .= $this->style;
      }
      $this->writeHTML($this->headerHTML, FALSE, 0, TRUE, 0);
    }
  }

  /**
   * {@inheritDoc}
   */
  public function Footer() {
    if (!empty($this->footerHTML)) {
      if (!empty($this->style)) {
        $this->footerHTML .= $this->style;
      }
      $this->writeHTMLCell(NULL, $this->footerHeight, NULL, -$this->footerHeight, $this->footerHTML);
    }
    $totalPage = $this->getAliasNbPages();
    $currentPage = $this->getAliasNumPage();
    if ($totalPage > 1 && $currentPage > 1) {
      $page = $this->t('Page') . ' ' . $currentPage . '/' . $totalPage;
      $this->SetY(-10);
      $this->Cell(0, 5, $page, FALSE, FALSE, 'R');
    }
  }

  /**
   * {@inheritDoc}
   */
  public function getReader() {
    return current($this->readers)->getParser();
  }

}
