<?php

namespace Drupal\fpdi_print;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StreamWrapper\StreamWrapperManagerInterface;
use Drupal\Core\Url;
use Drupal\Component\Utility\Html;

/**
 * The print builder service.
 */
class PrintBuilder {

  /**
   * The Entity Type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The stream wrapper manager.
   *
   * @var \Drupal\Core\StreamWrapper\StreamWrapperManagerInterface
   */
  protected $streamWrapperManager;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * Contains the configuration object factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * FileDownloadController constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type mananager service.
   * @param \Drupal\Core\StreamWrapper\StreamWrapperManagerInterface $streamWrapperManager
   *   The stream wrapper manager.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Configuration service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, StreamWrapperManagerInterface $streamWrapperManager, AccountInterface $current_user, ConfigFactoryInterface $config_factory) {
    $this->entityTypeManager = $entity_type_manager;
    $this->streamWrapperManager = $streamWrapperManager;
    $this->currentUser = $current_user;
    $this->configFactory = $config_factory;
  }

  /**
   * Array x, y coordinate positions in the page, the page number starts with 1.
   *
   * @param string $positions
   *   Pdf template file path or fid.
   * @param string $filePdfTemplate
   *   Define array header
   *   ['logo'|'width'|'title'|'string'|'text_color'|'line_color'].
   * @param bool $header
   *   Define array footer ['text_color'|'line_color'].
   * @param bool $footer
   *   Have footer.
   *
   * @return \Drupal\fpdi_print\Pdf|\FPDM
   *   Return file pdf.
   */
  public function getPdf($positions = '', $filePdfTemplate = '', $header = FALSE, $footer = FALSE) {
    $config = $this->configFactory->get('fpdi_print.settings');
    $pdf = new Pdf();
    $form = [];
    if (!empty($header) && is_string($header)) {
      $pdf->headerHTML = $header;
    }
    if (!empty($footer) && is_string($footer)) {
      $pdf->footerHTML = $footer;
      if (!empty($config->get('footer_height'))) {
        $pdf->footerHeight = $config->get('footer_height');
      }
    }
    if (!empty($header) && is_array($header)) {
      $pdf->header = $header;
    }
    else {
      $pdf->setPrintHeader($header);
    }
    if (is_array($footer)) {
      $pdf->footer = $footer;
    }
    else {
      $pdf->setPrintFooter($footer);
    }
    if (!empty($this->currentUser)) {
      $pdf->SetAuthor($this->currentUser->getDisplayName());
    }

    $pageCount = 1;

    $hasTemplate = FALSE;

    // If file pdf template is fid.
    if (!empty($filePdfTemplate)) {
      $filePdfTemplate = $this->getPath($filePdfTemplate);
      $pageCount = $pdf->setSourceFile($filePdfTemplate);
      /* in case of form pdf we read form
      $reader = $pdf->getReader();
      $value = $reader->readValue();
      $buffer = $reader->getStreamReader()->getBuffer();
      $parseBuffer = explode("\n",$buffer);
       */
      $hasTemplate = TRUE;
    }
    foreach (range(1, $pageCount) as $page) {
      if ($hasTemplate) {
        // Import page 1.
        $templateId = $pdf->importPage($page);
        // Get the size of the imported page.
        $size = $pdf->getTemplateSize($templateId);

        // Create a page (landscape or portrait depending on
        // the imported page size).
        if ($size[0] > $size[1]) {
          $pdf->AddPage('L', [$size[0], $size[1]]);
        }
        else {
          $pdf->AddPage('P', [$size[0], $size[1]]);
        }
        // Use the imported page.
        $pdf->useTemplate($templateId);
      }
      else {
        $pdf->AddPage($config->get('orientation'), $config->get('page_format'));
      }
      if (!empty($config->get('default_css'))) {
        $pdf->style = '<style>' . $this->getStyle($config->get('default_css')) . '</style>';
      }
      $keyPosition = ["text", "image", "html"];
      if (!empty($positions[$page])) {
        foreach ($positions[$page] as $position) {
          if (!empty(array_intersect(array_keys($position), $keyPosition))) {
            if (!empty($position['form'])) {
              $form[$page][$position['form']] = $position;
              // @todo 2 options we can Find form field coordinates https://www.setasign.com/products/setapdf-core/demos/find-form-field-coordinates/
              // or use http://www.fpdf.org/en/script/script93.php
              continue;
            }
            if (empty($position['x'])) {
              $position['x'] = 0;
            }
            if (empty($position['y'])) {
              $position['y'] = 0;
            }
            if (!empty($position['text'])) {
              $position['text'] = $this->formatText($position['text']);
              $pdf->SetXY($position['x'], $position['y']);
              if (!empty($position['height'])) {
                $pdf->MultiCell(0, $position['height'], $position['text']);
              }
              else {
                $pdf->Write(0, $position['text']);
              }
            }
            if (!empty($position['image'])) {
              // Check image base 64.
              if (strpos($position['image'], ';base64,') !== FALSE) {
                $img_base64_encoded = str_replace("data:", "", $position['image']);
                $position['image'] = "@" . base64_decode(preg_replace('#^image/[^;]+;base64,#', '', $img_base64_encoded));
                $imgType = "base64";
              }
              else {
                $position['image'] = $this->formatImage($position['image']);
                $path_parts = pathinfo($position['image']);
                $imgType = strtolower($path_parts['extension']);
                $img_width = '';
                if (!empty($position['width'])) {
                  $img_width = $position['width'];
                }
              }

              if (in_array($imgType, ['eps', 'ai'])) {
                $this->ImageEps($position['image'], $position['x'], $position['y'], $img_width);
              }
              elseif ($imgType == 'svg') {
                $this->ImageSVG($position['image'], $position['x'], $position['y'], $img_width);
              }
              else {
                [$img_width, $img_height] = getimagesize($position['image']);
                if (!empty($position['width'])) {
                  $img_width = $position['width'];
                  $img_height = $position['height'];
                }
                $pdf->Image($position['image'], $position['x'], $position['y'], $img_width, $img_height, $imgType != "base64" ? $imgType : '');
              }
            }
            if (!empty($position['html'])) {
              $pdf->SetXY($position['x'], $position['y']);
              if (!empty($position['height'])) {
                if (!empty($pdf->style)) {
                  $position['html'] .= $pdf->style;
                }
                $pdf->MultiCell(0, $position['height'], $position['html'], 0, 'L', 0, 1, $position['x'], $position['y'], TRUE, 0, TRUE);
              }
              else {
                if (!empty($pdf->style)) {
                  $position['html'] = $pdf->style . $position['html'];
                }
                $pdf->writeHTML($position['html'], TRUE, FALSE, TRUE, FALSE, '');
              }
            }
          }
        }
      }
    }

    if (!empty($form)) {
      $fields = [];
      foreach ($form as $page => $pageFields) {
        foreach ($pageFields as $field => $fieldValue) {
          $fields[$field] = $fieldValue['text'];
        }
      }
      $pdf = new \FPDM($filePdfTemplate);
      // Second param: false if fields value are in ISO-8859-1, true if UTF-8.
      $pdf->Load($fields, TRUE);
      $pdf->Merge();
      /*
      $generated =  \Drupal::service('file_system')->getTempDirectory().
      "/tempForm.pdf";
      $fpdf->Output("F", $generated);
      //merge to file source
      for($i = 1; $i <= $pageCount; $i++){
      $pageSource = $pdf->importPage($i);
      $pdf->useTemplate($pageSource);
      $countB = $pdf->setSourceFile($generated);
      // If page exists on it
      if($i <= $countB){
      $pageB = $pdf->importPage($i);
      $pdf->useTemplate($pageB);
      }
      }*/
    }
    return $pdf;
  }

  /**
   * Filter value.
   */
  protected function formatText($value) {
    $value = Html::decodeEntities($value);
    $value = preg_replace('/<br(\s+\/)/', "\n", $value);
    $value = trim(strip_tags($value));
    return $value;
  }

  /**
   * Get image path.
   */
  protected function formatImage($img) {
    $img = Html::decodeEntities($img);
    $img = trim(strip_tags($img, '<img>'));
    $doc = new \DOMDocument();
    $doc->loadHTML($img);
    $xpath = new \DOMXPath($doc);
    $src = $xpath->evaluate("string(//img/@src)");
    if (!empty($src)) {
      $img = $src;
    }
    $filename = $this->getPath($img);
    $is_picture = is_file($filename) && is_readable($filename) && getimagesize($filename);
    if ($is_picture) {
      return $filename;
    }
    return FALSE;
  }

  /**
   * {@inheritDoc}
   */
  protected function hex2rgb($hex_color) {
    $values = str_replace('#', '', $hex_color);
    switch (strlen($values)) {
      case 3;
        [$r, $g, $b] = sscanf($values, "%1s%1s%1s");
        return [hexdec("$r$r"), hexdec("$g$g"), hexdec("$b$b")];

      case 6;
        return array_map('hexdec', sscanf($values, "%2s%2s%2s"));

      default:
        return FALSE;
    }
  }

  /**
   * {@inheritDoc}
   */
  protected function getRealPathFromFid(int $fid) {
    $file = $this->entityTypeManager->getStorage('file')->load($fid);
    if (!empty($file)) {
      $uri = $file->getFileUri();
      $stream_manager = $this->streamWrapperManager->getViaUri($uri);
      return $stream_manager->realpath();
    }
    return FALSE;
  }

  /**
   * Convert url to patch.
   */
  public function getPath($url) {
    $filename = $url;
    if (is_numeric($url)) {
      $filename = $this->getRealPathFromFid($url);
    }
    elseif (!file_exists($url)) {
      // Remove url absolute.
      $urlSite = Url::fromUserInput('/', ['absolute' => TRUE])
        ->toString();
      if (strpos($url, $urlSite) !== FALSE) {
        $url = str_replace($urlSite, '', $url);
        $urlParse = parse_url($url);
        // Convert url to path.
        $filename = \Drupal::root() . $urlParse['path'];
      }
    }
    return $filename;
  }

  /**
   * {@inheritDoc}
   */
  protected function getStyle($css) {
    $filename = $this->getPath($css);
    return file_get_contents($filename);
  }

}
