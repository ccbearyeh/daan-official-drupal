<?php

namespace Drupal\fpdi_print\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Render\RendererInterface as CoreRendererInterface;
use Drupal\Core\Template\TwigEnvironment;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Yaml\Yaml;

/**
 * Controller class for printing Views.
 */
class ViewPrintController extends ControllerBase {

  /**
   * The Entity Type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The current request.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $currentRequest;

  /**
   * The Twig environment.
   *
   * @var \Drupal\Core\Template\TwigEnvironment
   */
  protected $twig;

  /**
   * The renderer for renderable arrays.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * Ratio 100px = 0.27mm (75dpi).
   *
   * @var string
   */
  public $rationPixel2mm = 0.27;

  /**
   * View object.
   *
   * @var object
   */
  public $view;

  /**
   * Data fileds.
   *
   * @var array
   */
  public $dataFields;

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystem
   */
  protected $fileSystem;

  /**
   * The file URL generator.
   *
   * @var \Drupal\Core\File\FileUrlGeneratorInterface
   */
  protected $fileUrlGenerator;

  /**
   * {@inheritdoc}
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, Request $current_request, TwigEnvironment $twig, CoreRendererInterface $renderer, FileSystemInterface $file_system, FileUrlGeneratorInterface $file_url_generator = NULL) {
    $this->entityTypeManager = $entity_type_manager;
    $this->currentRequest = $current_request;
    $this->twig = $twig;
    $this->renderer = $renderer;
    $this->fileSystem = $file_system;
    $this->fileUrlGenerator = $file_url_generator;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('request_stack')->getCurrentRequest(),
      $container->get('twig'),
      $container->get('renderer'),
      $container->get('file_system'),
      $container->get('file_url_generator')
    );
  }

  /**
   * Print an entity to the selected format.
   *
   * {@inheritdoc}
   */
  public function viewPrint($view_name, $display_id, $option_id) {
    // Create the Print engine plugin.
    /** @var \Drupal\views\Entity\View $view */
    $loadView = $this->entityTypeManager->getStorage('view')->load($view_name);
    $this->view = $loadView->getExecutable();
    $this->view->setDisplay($display_id);
    [$areaType, $optionId] = explode('-', $option_id);
    $option = $this->view->getHandlers($areaType);
    $positions = [];
    if ($args = $this->currentRequest->query->get('view_args')) {
      $this->view->setArguments($args);
    }
    $this->view->execute();

    foreach ($this->view->field as $id => $field) {
      if (!empty($this->view->result)) {
        $result = current($this->view->result);
        $this->dataFields[$id] = $field->theme($result);
      }
    }
    $allowExtensions = ['png', 'jpge', 'jpg', 'ai', 'esp', 'svg'];
    $filePdfTemplate = NULL;
    $filePdfName = NULL;
    $viewHeader = NULL;
    $viewFooter = NULL;
    if (!empty($option[$optionId])) {
      $positionText = $option[$optionId]['position_text'];
      $filePdfTemplate = $option[$optionId]['file_pdf'];
      $filePdfName = $option[$optionId]['file_pdf_name'];
      $print_logo = $option[$optionId]['print_logo'];

      if (!empty($positionText)) {
        $positionText = Yaml::parse($positionText);
        if (!empty($positionText)) {
          $positions = $this->getPosition($positionText);
        }
      }
      else {
        foreach (['header', 'footer', 'empty'] as $area_type) {
          $handlers = &$this->view->display_handler->getHandlers($area_type);
          if (!empty($handlers)) {
            foreach ($handlers as $idHandler => $valHandler) {
              if ($valHandler->field == 'area_fpdi_print_views') {
                unset($handlers[$idHandler]);
                continue;
              }
              $viewsHeaderFooter[$area_type][] = $handlers[$idHandler];
              unset($handlers[$idHandler]);
            }
          }
        }
        $renderView = $this->view->render();
        $positions[1] = [
          ['html' => (string) $this->renderer->render($renderView)],
        ];
        if (!empty($viewsHeaderFooter['header'])) {
          foreach ($viewsHeaderFooter['header'] as $header) {
            $render = $header->render();
            $viewHeader .= (string) $this->renderer->render($render);
          }
        }
        if ($print_logo) {
          $site_config = $this->config('system.site');
          $logo = \Drupal::root() . $this->fileUrlGenerator->generateString(
            theme_get_setting('logo.url')
          );
          $path_parts = pathinfo($logo);
          $logo_width_max = 10;
          $logo_height_max = 11;
          if (in_array(strtolower($path_parts['extension']), $allowExtensions)) {
            if ($path_parts['extension'] != 'svg') {
              [$logo_width, $logo_height] = getimagesize($logo);
              $logo_width_max = ceil($logo_height_max * $logo_width / $logo_height);
            }
          }
          else {
            $logo = NULL;
          }
          $headerDefault = [
            'logo' => $logo,
            'width' => $logo_width_max,
            'title' => $site_config->get('name'),
            'string' => $site_config->get('slogan'),
          ];
          if (!empty($viewHeader)) {
            $viewHeader = $headerDefault + ['html' => $viewHeader];
          }
        }
        if (!empty($viewsHeaderFooter['footer'])) {
          foreach ($viewsHeaderFooter['footer'] as $footer) {
            $render = $footer->render();
            $viewFooter .= (string) $this->renderer->render($render);
          }
        }
      }
    }

    $this->moduleHandler()->alter('fpdi_print_views', $positions, $this->view, $filePdfTemplate, $viewHeader, $viewFooter);
    $pdf = \Drupal::service('fpdi_print.print_builder')->getPDF($positions, $filePdfTemplate, $viewHeader, $viewFooter);
    $response = new StreamedResponse();
    if (!empty($filePdfName)) {
      $filePdfName = trim(strip_tags($this->convertTokenValue($filePdfName)));
      $extractPath = explode(DIRECTORY_SEPARATOR, $filePdfName);
      if (count($extractPath) > 1) {
        $filePdfName = array_pop($extractPath);
        $path = implode(DIRECTORY_SEPARATOR, $extractPath);
        if (!file_exists($path)) {
          $is_created = $this->fileSystem->mkdir($path);
          if (!$is_created) {
            $path = FALSE;
          }
        }
      }
      $pdfOutput = 'D';
      $filePdfName = trim(str_replace(['.pdf', '.PDF'], '', $filePdfName)) . '.pdf';
      if (!empty($path)) {
        $pdfOutput = 'FD';
        $filePdfName = implode(DIRECTORY_SEPARATOR, [$path, $filePdfName]);
      }
      return $response->setCallback(function () use ($pdf, $filePdfName, $pdfOutput) {
        ob_clean();
        $pdf->Output($filePdfName, $pdfOutput);
      });
    }

    return $response->setCallback(function () use ($pdf) {
      ob_clean();
      $handle = fopen('php://output', 'w+');
      $pdf->Output();
      fclose($handle);
    });
  }

  /**
   * Validate that the current user has access.
   *
   * We need to validate that the user is allowed to access views display id.
   *
   * @param string $view_name
   *   The view name.
   * @param string $display_id
   *   The view display to render.
   * @param string $option_id
   *   The option id.
   *
   * @return \Drupal\Core\Access\AccessResult
   *   The access result object.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function checkAccess($view_name, $display_id, $option_id = '') {
    $view = $this->entityTypeManager->getStorage('view')->load($view_name)->getExecutable();
    $account = $this->currentUser();

    // Check the content permission.
    $result = AccessResult::allowedIfHasPermission($account, 'access content');

    // Also check the permissions defined by the view.
    return $result->isAllowed() && $view->access($display_id, $account) ? $result : AccessResult::forbidden();
  }

  /**
   * {@inheritdoc}
   */
  protected function convertTokenValue($text) {
    $style = $this->view->getStyle();
    $token = $style->tokenizeValue($text, 0);
    $value = $style->globalTokenReplace($token);
    $view_result = $this->view->result;
    if (strpos($text, '{{') !== FALSE && !empty($view_result)) {
      $value = (string) $this->twig->renderInline($text, $this->dataFields);
    }
    return $value;
  }

  /**
   * {@inheritdoc}
   */
  public function getPosition($positionText) {
    $positions = [];
    $pageNoDefault = 1;
    $imageWidthDefault = 80;
    foreach ($positionText as &$position) {
      $pageNo = !empty($position['page']) ? $position['page'] : $pageNoDefault;
      if (!empty($position['text'])) {
        $position['text'] = $this->convertTokenValue($position['text']);
        $positions[$pageNo][] = $position;
      }
      elseif (!empty($position['image'])) {
        $position['image'] = $this->convertTokenValue($position['image']);
        $doc = new \DOMDocument();
        @$doc->loadHTML($position['image']);
        $tags = $doc->getElementsByTagName('img');
        // If there is more than 1 image we move next image 80 point.
        $widthDefault = !empty($position['width']) ? $position['width'] : $imageWidthDefault;
        if (!empty($tags->length)) {
          foreach ($tags as $i => $tag) {
            $width = !empty($position['width']) ? $position['width'] : $widthDefault = $tag->getAttribute('width');
            $height = !empty($position['height']) ? $position['height'] : $tag->getAttribute('height');
            $positions[$pageNo][] = [
              'image' => $tag->getAttribute('src'),
              'x' => $position['x'] + $i * $widthDefault * $this->rationPixel2mm,
              'y' => $position['y'],
              'width' => $width * $this->rationPixel2mm,
              'height' => $height * $this->rationPixel2mm,
            ];
          }
        }
        else {
          $positions[$pageNo][] = $position;
        }
      }
      elseif (!empty($position['html'])) {
        $position['html'] = $this->convertTokenValue($position['html']);
        $positions[$pageNo][] = $position;
      }
    }
    return !empty($positions) ? $positions : FALSE;
  }

}
