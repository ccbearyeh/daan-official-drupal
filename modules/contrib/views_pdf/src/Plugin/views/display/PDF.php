<?php
declare(strict_types=1);

namespace Drupal\views_pdf\Plugin\views\display;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\CacheableResponse;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Menu\MenuParentFormSelectorInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Routing\RouteProviderInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Url;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\views\Plugin\views\display\PathPluginBase;
use Drupal\views\Plugin\views\display\ResponseDisplayPluginInterface;
use Drupal\views\Views;
use Drupal\views_pdf\Entity\ViewsPdfTemplate;
use Drupal\views_pdf\PdfLibrary\FPDI;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The plugin that handles a PDF.
 *
 * @ingroup views_display_plugins
 *
 * @ViewsDisplay(
 *   id = "pdf",
 *   title = @Translation("PDF"),
 *   help = @Translation("Display the view as a PDF page."),
 *   uses_menu_links = TRUE,
 *   uses_route = TRUE,
 *   contextual_links_locations = {"page"},
 *   admin = @Translation("PDF"),
 *   returns_response = TRUE
 * )
 */
class PDF extends PathPluginBase implements ResponseDisplayPluginInterface {

  /**
   * {@inheritdoc}
   */
  protected $ajaxEnabled = FALSE;

  /**
   * {@inheritdoc}
   */
  protected $usesPager = FALSE;

  // TODO: Review use case.
  public int $numberOfRecords;

  /** @var \Drupal\Core\Render\RendererInterface */
  protected $renderer;

  /** @var FPDI */
  public $pdf;

  /**
   * PDF constructor.
   *
   * @param array $configuration
   * @param $plugin_id
   * @param $plugin_definition
   * @param \Drupal\Core\Routing\RouteProviderInterface $route_provider
   * @param \Drupal\Core\State\StateInterface $state
   * @param \Drupal\Core\Render\RendererInterface $renderer
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    RouteProviderInterface $route_provider,
    StateInterface $state,
    /** @var RendererInterface */
    RendererInterface $renderer,
    protected EntityStorageInterface $menuStorage,
    protected MenuParentFormSelectorInterface|null $parentFormSelector,
    protected ConfigFactoryInterface $configFactory
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $route_provider, $state);
    if (!$parentFormSelector) {
      @trigger_error('Calling ' . __METHOD__ . '() without the $parent_form_selector argument is deprecated in drupal:9.3.0 and the $parent_form_selector argument will be required in drupal:10.0.0. See https://www.drupal.org/node/3027559', E_USER_DEPRECATED);
      $parent_form_selector = \Drupal::service('menu.parent_form_selector');
    }
    $this->renderer = $renderer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) : PDF {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('router.route_provider'),
      $container->get('state'),
      $container->get('renderer'),
      $container->get('entity_type.manager')->getStorage('menu'),
      $container->get('menu.parent_form_selector'),
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getRoute($view_id, $display_id) : \Symfony\Component\Routing\Route {
    $route = parent::getRoute($view_id, $display_id);

    $route->setRequirement('_format', 'pdf');

    return $route;
  }

  /**
   * {@inheritdoc}
   */
  public function getType() {
    return 'pdf';
  }

  public static function buildResponse($view_id, $display_id, array $args = []) : StreamedResponse {
    $build = static::buildBasicRenderable($view_id, $display_id, $args);

    // Set up an empty response, so for example RSS can set the proper
    // Content-Type header.
    $response = new CacheableResponse('', 200);
    $build['#response'] = $response;

    /** @var \Drupal\Core\Render\RendererInterface $renderer */
    $renderer = \Drupal::service('renderer');

    $renderer->renderRoot($build);
    /** @var \Drupal\views\ViewExecutable $view */
    $view = $build['view_build']['#view'];

    $response->setContent($view->pdf->Output());
    $cache_metadata = CacheableMetadata::createFromRenderArray($build);
    $response->addCacheableDependency($cache_metadata);

    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function execute() {
    parent::execute();


    // Defines external configuration for TCPDF library
    if (!defined('K_TCPDF_EXTERNAL_CONFIG')) {
      $tcpdf_path = FPDI::getPathTcpdf();
      $cache_path = 'public://views_pdf_cache/';

      \Drupal::service('file_system')->prepareDirectory($cache_path);

      global $base_url;

      define('K_TCPDF_EXTERNAL_CONFIG', TRUE);
      defined('K_PATH_MAIN') || define('K_PATH_MAIN', dirname($_SERVER['SCRIPT_FILENAME']));
      defined('K_PATH_URL') || define('K_PATH_URL', $base_url);
      defined('K_PATH_FONTS') || define('K_PATH_FONTS', $tcpdf_path . '/fonts/');
      defined('K_PATH_CACHE') || define('K_PATH_CACHE', \Drupal::service('file_system')->realpath($cache_path));
      defined('K_PATH_IMAGES') || define('K_PATH_IMAGES', '');
      defined('K_BLANK_IMAGE') || define('K_BLANK_IMAGE', $tcpdf_path . '/examples/images/_blank.png');
      defined('K_CELL_HEIGHT_RATIO') || define('K_CELL_HEIGHT_RATIO', 1.25);
      defined('K_SMALL_RATIO') || define('K_SMALL_RATIO', 2/3);
    }

    if ($this->getOption('default_page_format') === 'custom') {
      if (preg_match('~([0-9\.]+)x([0-9\.]+)~', $this->getOption('default_page_format_custom'), $result)) {
        $format[0] = $result[1]; // width
        $format[1] = $result[2]; // height
      }
      else {
        $format = 'A4';
      }

    }
    else {
      $format = $this->getOption('default_page_format');
    }

    $orientation = $this->getOption('default_page_orientation'); // P or L
    $unit = $this->getOption('unit');

    $this->view->pdf = new FPDI($orientation, $unit, $format);

    $this->view->pdf->SetMargins($this->getOption('margin_left'), $this->getOption('margin_top'), $this->getOption('margin_right'), TRUE);

    $this->view->pdf->SetAutoPageBreak(TRUE, $this->getOption('margin_bottom'));

    $this->view->pdf->setDefaultFontSize($this->getOption('default_font_size'));
    $this->view->pdf->setDefaultFontFamily($this->getOption('default_font_family'));
    $this->view->pdf->setDefaultFontStyle($this->getOption('default_font_style'));

    $this->view->pdf->setRTL($this->getOption('right_to_left'));

    $this->view->pdf->setDefaultTextAlign($this->getOption('default_text_align'));
    $this->view->pdf->setDefaultFontColor($this->getOption('default_font_color'));

    $this->view->pdf->SetTitle($this->view->getTitle());

    $this->view->pdf->setViewsHeaderFooter($this);

    return $this->view->render();
  }

  /**
   * {@inheritdoc}
   */
  public function preview() : mixed {
    return $this->view->render();
  }

  /**
   * {@inheritdoc}
   */
  public function render() {

    // Set default code
    $this->view->pdf->SetFont('');

    // Add leading pages
    if (!empty($this->getOption('leading_template'))) {
      $path = $this->view->pdf->getTemplatePath($this->getOption('leading_template'));
      $this->view->pdf->addPdfDocument($path, 'leading');
    }

    // Set the default background template
    if (!empty($this->getOption('template'))) {
      $path = $this->view->pdf->getTemplatePath($this->getOption('template'));
      $this->view->pdf->setDefaultPageTemplate($path, 'main');
    }

    // Clear the leading/succeed flag.
    $this->view->pdf->addPdfDocument();

    $build = $this->view->style_plugin->render($this->view->result);

    $this->applyDisplayCacheabilityMetadata($build);

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultableSections($section = NULL) : array {
    $sections = (array) parent::defaultableSections($section);

    if (in_array($section, ['style', 'row', 'style_options', 'style_plugin', 'row_options', 'row_plugin'])) {
      return [];
    }

    // Tell views our sitename_title option belongs in the title section.
    if ($section === 'title') {
      $sections[] = 'sitename_title';
    }
    elseif (!$section) {
      $sections['title'][] = 'sitename_title';
    }

    return $sections;
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    $viewsPdfSettings = $this->configFactory->get('views_pdf.settings');

    $options['displays'] = ['default' => []];

    // Overrides for standard stuff.
    $options['style']['contains']['type']['default'] = 'pdf_unformatted';
    $options['style']['contains']['options']['default'] = ['mission_description' => FALSE, 'description' => ''];
    $options['sitename_title']['default'] = FALSE;
    $options['row']['contains']['type']['default'] = 'pdf_fields';
    $options['defaults']['default']['style'] = FALSE;
    $options['defaults']['default']['row'] = FALSE;

    $options['menu'] = [
      'contains' => [
        'type' => ['default' => 'none'],
        'title' => ['default' => ''],
        'description' => ['default' => ''],
        'weight' => ['default' => 0],
        'enabled' => ['default' => TRUE],
        'menu_name' => ['default' => 'main'],
        'parent' => ['default' => ''],
        'context' => ['default' => ''],
        'expanded' => ['default' => FALSE],
      ],
    ];
    $options['tab_options'] = [
      'contains' => [
        'type' => ['default' => 'none'],
        'title' => ['default' => ''],
        'description' => ['default' => ''],
        'weight' => ['default' => 0],
      ],
    ];

    // New Options
    $options['default_page_format'] = ['default' => $viewsPdfSettings->get('default_page_format')];
    $options['default_page_format_custom'] = ['default' => $viewsPdfSettings->get('default_page_format_custom')];
    $options['default_page_orientation'] = ['default' => $viewsPdfSettings->get('default_page_orientation')];
    $options['unit'] = ['default' => $viewsPdfSettings->get('unit')];
    $options['margin_left'] = ['default' => $viewsPdfSettings->get('margin_left')];
    $options['margin_right'] = ['default' => $viewsPdfSettings->get('margin_right')];
    $options['margin_top'] = ['default' => $viewsPdfSettings->get('margin_top')];
    $options['margin_bottom'] = ['default' => $viewsPdfSettings->get('margin_bottom')];

    $options['leading_template'] = ['default' => $viewsPdfSettings->get('leading_template')];
    $options['template'] = ['default' => $viewsPdfSettings->get('template')];
    $options['succeed_template'] = ['default' => $viewsPdfSettings->get('succeed_template')];

    $options['default_font_family'] = ['default' => $viewsPdfSettings->get('default_font_family')];
    $options['default_font_style'] = ['default' => $viewsPdfSettings->get('default_font_style') ?? []];
    $options['right_to_left'] = ['default' => $viewsPdfSettings->get('right_to_left')];
    $options['default_font_size'] = ['default' => $viewsPdfSettings->get('default_font_size')];
    $options['default_text_align'] = ['default' => $viewsPdfSettings->get('default_text_align')];
    $options['default_font_color'] = ['default' => $viewsPdfSettings->get('default_font_color')];
    $options['default_text_hyphenate'] = ['default' => $viewsPdfSettings->get('default_text_hyphenate')];

    $options['header_margin'] = ['default' => $viewsPdfSettings->get('header_margin')];
    $options['header_font_family'] = ['default' => $viewsPdfSettings->get('header_font_family')];
    $options['header_font_style'] = ['default' => $viewsPdfSettings->get('header_font_style') ?? []];
    $options['header_font_size'] = ['default' => $viewsPdfSettings->get('header_font_size')];
    $options['header_text_align'] = ['default' => $viewsPdfSettings->get('header_text_align')];
    $options['header_font_color'] = ['default' => $viewsPdfSettings->get('header_font_color')];

    $options['footer_spacing'] = ['default' => $viewsPdfSettings->get('footer_spacing')];
    $options['footer_font_family'] = ['default' => $viewsPdfSettings->get('footer_font_family')];
    $options['footer_font_style'] = ['default' => $viewsPdfSettings->get('footer_font_style') ?? []];
    $options['footer_font_size'] = ['default' => $viewsPdfSettings->get('footer_font_size')];
    $options['footer_text_align'] = ['default' => $viewsPdfSettings->get('footer_text_align')];
    $options['footer_font_color'] = ['default' => $viewsPdfSettings->get('footer_font_color')];

    $options['css_file'] = ['default' => ''];

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function newDisplay() {
    parent::newDisplay();

    // Set the default row style. Ideally this would be part of the option
    // definition, but in this case it's dependent on the view's base table,
    // which we don't know until init().
    if (empty($this->options['row']['type']) || $this->options['row']['type'] === 'pdf_fields') {
      $row_plugins = Views::fetchPluginNames('row', $this->getType(), [$this->view->storage->get('base_table')]);
      $default_row_plugin = key($row_plugins);

      $options = $this->getOption('row');
      $options['type'] = $default_row_plugin;
      $this->setOption('row', $options);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function optionsSummary(&$categories, &$options) {
    parent::optionsSummary($categories, $options);

    $fonts = FPDI::getAvailableFontsCleanList();

    // Change Page title:
    $categories['page'] = array(
      'title' => t('PDF settings'),
      'column' => 'second',
      'build' => array(
        '#weight' => -10,
      ),
    );


  // Since we're childing off the 'path' type, we'll still *call* our
    // category 'page' but let's override it so it says feed settings.
    $categories['page'] = [
      'title' => $this->t('PDF settings'),
      'column' => 'second',
      'build' => [
        '#weight' => -10,
      ],
    ];

    if ($this->getOption('sitename_title')) {
      $options['title']['value'] = $this->t('Using the site name');
    }

    $displays = array_filter($this->getOption('displays'));
    if (count($displays) > 1) {
      $attach_to = $this->t('Multiple displays');
    }
    elseif (count($displays) === 1) {
      $display = array_shift($displays);
      $displays = $this->view->storage->get('display');
      if (!empty($displays[$display])) {
        $attach_to = $displays[$display]['display_title'];
      }
    }

    if (!isset($attach_to)) {
      $attach_to = $this->t('None');
    }

    $options['displays'] = [
      'category' => 'page',
      'title' => $this->t('Attach to'),
      'value' => $attach_to,
    ];

    $menu = $this->getOption('menu');
    if (!is_array($menu)) {
      $menu = ['type' => 'none'];
    }

    $menu_str = match($menu['type']) {
      'normal' => $this->t('Normal: @title', ['@title' => $menu['title']]),
      'tab', 'default tab' => $this->t('Tab: @title', ['@title' => $menu['title']]),
      'none' => $this->t('No menu'),
       default => $this->t('No menu'),
    };

    $options['menu'] = [
      'category' => 'page',
      'title' => $this->t('Menu'),
      'value' => \views_ui_truncate($menu_str, 24),
    ];

    // This adds a 'Settings' link to the style_options setting if the style
    // has options.
    if ($menu['type'] == 'default tab') {
      $options['menu']['setting'] = $this->t('Parent menu link');
      $options['menu']['links']['tab_options'] = $this->t('Change settings for the parent menu');
    }

    // Add for pdf page settings
    $options['pdf_page'] = array(
      'category' => 'page',
      'title' => $this->t('PDF Page Settings'),
      'value' => $this->getOption('default_page_format'),
      'desc' => $this->t('Define some PDF specific settings.'),
    );

    // Add for pdf font settings
    $options['pdf_fonts'] = array(
      'category' => 'page',
      'title' => $this->t('PDF Fonts Settings'),
      'value' => $this->t(':family at :size pt', array(':family' => $fonts[$this->getOption('default_font_family')], ':size' => $this->getOption('default_font_size'))),
      'desc' => $this->t('Define some PDF specific settings.'),
    );

    // Add for pdf header/footer settings
    $options['pdf_header'] = array(
      'category' => 'page',
      'title' => $this->t('PDF Header & Footer'),
      'value' => $this->t('Settings'),
      'desc' => $this->t('Define PDF settings for header & footer.'),
    );

    // add for pdf template settings
    if (!empty($this->getOption('leading_template')) ||
      !empty($this->getOption('template')) ||
      !empty($this->getOption('succeed_template'))
    ) {
      $isAnyTemplate = $this->t('Yes');
    }
    else {
      $isAnyTemplate = $this->t('No');
    }

    $options['pdf_template'] = array(
      'category' => 'page',
      'title' => $this->t('PDF Template Settings'),
      'value' => $isAnyTemplate,
      'desc' => $this->t('Define some PDF specific settings.'),
    );

    if ($this->getOption('css_file') === '') {
      $css_file = $this->t('None');
    }
    else {
      $css_file = $this->getOption('css_file');
    }

    $options['css'] = array(
      'category' => 'page',
      'title' => $this->t('CSS File'),
      'value' => $css_file,
      'desc' => $this->t('Define a CSS file attached to all HTML output.'),
    );


  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    switch ($form_state->get('section')) {
      case 'menu':
        $form['#title'] .= $this->t('Menu link entry');
        $form['menu'] = [
          '#prefix' => '<div class="clearfix">',
          '#suffix' => '</div>',
          '#tree' => TRUE,
        ];
        $menu = $this->getOption('menu');
        if (empty($menu)) {
          $menu = ['type' => 'none', 'title' => '', 'weight' => 0, 'expanded' => FALSE];
        }
        $form['menu']['type'] = [
          '#prefix' => '<div class="views-left-30">',
          '#suffix' => '</div>',
          '#title' => $this->t('Type'),
          '#type' => 'radios',
          '#options' => [
            'none' => $this->t('No menu entry'),
            'normal' => $this->t('Normal menu entry'),
            'tab' => $this->t('Menu tab'),
            'default tab' => $this->t('Default menu tab'),
          ],
          '#default_value' => $menu['type'],
        ];

        $form['menu']['title'] = [
          '#prefix' => '<div class="views-left-50">',
          '#title' => $this->t('Menu link title'),
          '#type' => 'textfield',
          '#default_value' => $menu['title'],
          '#states' => [
            'visible' => [
              [
                ':input[name="menu[type]"]' => ['value' => 'normal'],
              ],
              [
                ':input[name="menu[type]"]' => ['value' => 'tab'],
              ],
              [
                ':input[name="menu[type]"]' => ['value' => 'default tab'],
              ],
            ],
          ],
        ];
        $form['menu']['description'] = [
          '#title' => $this->t('Description'),
          '#type' => 'textfield',
          '#default_value' => $menu['description'],
          '#description' => $this->t("Shown when hovering over the menu link."),
          '#states' => [
            'visible' => [
              [
                ':input[name="menu[type]"]' => ['value' => 'normal'],
              ],
              [
                ':input[name="menu[type]"]' => ['value' => 'tab'],
              ],
              [
                ':input[name="menu[type]"]' => ['value' => 'default tab'],
              ],
            ],
          ],
        ];
        $form['menu']['expanded'] = [
          '#title' => $this->t('Show as expanded'),
          '#type' => 'checkbox',
          '#default_value' => !empty($menu['expanded']),
          '#description' => $this->t('If selected and this menu link has children, the menu will always appear expanded.'),
        ];

        $menu_parent = $menu['menu_name'] . ':' . $menu['parent'];
        $menu_link = 'views_view:views.' . $form_state->get('view')->id() . '.' . $form_state->get('display_id');
        $form['menu']['parent'] = $this->parentFormSelector->parentSelectElement($menu_parent, $menu_link);
        $form['menu']['parent'] += [
          '#title' => $this->t('Parent'),
          '#description' => $this->t('The maximum depth for a link and all its children is fixed. Some menu links may not be available as parents if selecting them would exceed this limit.'),
          '#attributes' => ['class' => ['menu-title-select']],
          '#states' => [
            'visible' => [
              [
                ':input[name="menu[type]"]' => ['value' => 'normal'],
              ],
              [
                ':input[name="menu[type]"]' => ['value' => 'tab'],
              ],
            ],
          ],
        ];
        $form['menu']['weight'] = [
          '#title' => $this->t('Weight'),
          '#type' => 'textfield',
          '#default_value' => $menu['weight'] ?? 0,
          '#description' => $this->t('In the menu, the heavier links will sink and the lighter links will be positioned nearer the top.'),
          '#states' => [
            'visible' => [
              [
                ':input[name="menu[type]"]' => ['value' => 'normal'],
              ],
              [
                ':input[name="menu[type]"]' => ['value' => 'tab'],
              ],
              [
                ':input[name="menu[type]"]' => ['value' => 'default tab'],
              ],
            ],
          ],
        ];
        $form['menu']['context'] = [
          '#title' => $this->t('Context'),
          '#suffix' => '</div>',
          '#type' => 'checkbox',
          '#default_value' => !empty($menu['context']),
          '#description' => $this->t('Displays the link in contextual links'),
          '#states' => [
            'visible' => [
              ':input[name="menu[type]"]' => ['value' => 'tab'],
            ],
          ],
        ];
        break;

      case 'tab_options':
        $form['#title'] .= $this->t('Default tab options');
        $tab_options = $this->getOption('tab_options');
        if (empty($tab_options)) {
          $tab_options = ['type' => 'none', 'title' => '', 'weight' => 0];
        }

        $form['tab_markup'] = [
          '#markup' => '<div class="js-form-item form-item description">' . $this->t('When providing a menu link as a tab, Drupal needs to know what the parent menu link of that tab will be. Sometimes the parent will already exist, but other times you will need to have one created. The path of a parent link will always be the same path with the last part left off. i.e, if the path to this view is <em>foo/bar/baz</em>, the parent path would be <em>foo/bar</em>.') . '</div>',
        ];

        $form['tab_options'] = [
          '#prefix' => '<div class="clearfix">',
          '#suffix' => '</div>',
          '#tree' => TRUE,
        ];
        $form['tab_options']['type'] = [
          '#prefix' => '<div class="views-left-25">',
          '#suffix' => '</div>',
          '#title' => $this->t('Parent menu link'),
          '#type' => 'radios',
          '#options' => ['none' => $this->t('Already exists'), 'normal' => $this->t('Normal menu link'), 'tab' => $this->t('Menu tab')],
          '#default_value' => $tab_options['type'],
        ];
        $form['tab_options']['title'] = [
          '#prefix' => '<div class="views-left-75">',
          '#title' => $this->t('Title'),
          '#type' => 'textfield',
          '#default_value' => $tab_options['title'],
          '#description' => $this->t('If creating a parent menu link, enter the title of the link.'),
          '#states' => [
            'visible' => [
              [
                ':input[name="tab_options[type]"]' => ['value' => 'normal'],
              ],
              [
                ':input[name="tab_options[type]"]' => ['value' => 'tab'],
              ],
            ],
          ],
        ];
        $form['tab_options']['description'] = [
          '#title' => $this->t('Description'),
          '#type' => 'textfield',
          '#default_value' => $tab_options['description'],
          '#description' => $this->t('If creating a parent menu link, enter the description of the link.'),
          '#states' => [
            'visible' => [
              [
                ':input[name="tab_options[type]"]' => ['value' => 'normal'],
              ],
              [
                ':input[name="tab_options[type]"]' => ['value' => 'tab'],
              ],
            ],
          ],
        ];
        $form['tab_options']['weight'] = [
          '#suffix' => '</div>',
          '#title' => $this->t('Tab weight'),
          '#type' => 'textfield',
          '#default_value' => $tab_options['weight'],
          '#size' => 5,
          '#description' => $this->t('If the parent menu link is a tab, enter the weight of the tab. Heavier tabs will sink and the lighter tabs will be positioned nearer to the first menu link.'),
          '#states' => [
            'visible' => [
              ':input[name="tab_options[type]"]' => ['value' => 'tab'],
            ],
          ],
        ];
        break;

      // PDF Form
      case 'pdf_page':
        $form['#title'] .= $this->t('PDF Page Options');
        $form['default_page_format'] = [
          '#type' => 'select',
          '#title' => $this->t('Default Page Format'),
          '#required' => TRUE,
          '#options' => FPDI::pdfGetPageFormats(),
          '#description' => $this->t('This is the default page format. If you specifiy a different format in the template section, this settings will be override.'),
          '#default_value' => $this->getOption('default_page_format'),
        ];
        $form['default_page_format_custom'] = [
          '#type' => 'textfield',
          '#title' => $this->t('Custom Page Format'),
          '#description' => $this->t('Here you can specifiy a custom page format. The schema is "[width]x[height]".'),
          '#default_value' => $this->getOption('default_page_format_custom'),
        ];
        $form['default_page_orientation'] = [
          '#type' => 'radios',
          '#title' => $this->t('Default Page Orientation'),
          '#required' => TRUE,
          '#options' => ['P' => $this->t('Portrait'), 'L' => $this->t('Landscape')],
          '#description' => $this->t('This is the default page orientation.'),
          '#default_value' => $this->getOption('default_page_orientation'),
        ];
        $form['unit'] = [
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
          '#default_value' => $this->getOption('unit'),
        ];
        $form['margin_left'] = [
          '#type' => 'textfield',
          '#title' => $this->t('Margin: Left'),
          '#required' => TRUE,
          '#default_value' => $this->getOption('margin_left'),
        ];
        $form['margin_right'] = [
          '#type' => 'textfield',
          '#title' => $this->t('Margin: Right'),
          '#required' => TRUE,
          '#default_value' => $this->getOption('margin_right'),
        ];
        $form['margin_top'] = [
          '#type' => 'textfield',
          '#title' => $this->t('Margin: Top'),
          '#required' => TRUE,
          '#default_value' => $this->getOption('margin_top'),
        ];
        $form['margin_bottom'] = [
          '#type' => 'textfield',
          '#title' => $this->t('Margin: Bottom'),
          '#required' => TRUE,
          '#default_value' => $this->getOption('margin_bottom'),
        ];
        break;

      case 'pdf_fonts':
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

        $form['#title'] .= $this->t('PDF Default Font Options');
        $form['description'] = [
          '#prefix' => '<div class="description form-item">',
          '#suffix' => '</div>',
          '#value' => $this->t('Here you specify a the default font settings for the document.'),
        ];
        $form['default_font_size'] = [
          '#type' => 'textfield',
          '#title' => $this->t('Font Size'),
          '#size' => 10,
          '#default_value' => $this->getOption('default_font_size'),
        ];
        $form['default_font_family'] = [
          '#type' => 'select',
          '#title' => $this->t('Font Family'),
          '#options' => $fonts,
          '#size' => 5,
          '#default_value' => $this->getOption('default_font_family'),
        ];
        $form['right_to_left'] = [
          '#type' => 'checkbox',
          '#title' => $this->t('Set text to be Right to Left'),
          '#description' => $this->t('For languages such arabic japanese with the rule right to left.'),
          '#default_value' => $this->getOption('right_to_left'),
        ];
        $form['default_font_style'] = [
          '#type' => 'checkboxes',
          '#title' => $this->t('Font Style'),
          '#options' => $font_styles,
          '#default_value' => $this->getOption('default_font_style'),
        ];
        $form['default_text_align'] = [
          '#type' => 'radios',
          '#title' => $this->t('Text Alignment'),
          '#options' => $align,
          '#default_value' => $this->getOption('default_text_align'),
        ];
        $form['default_text_hyphenate'] = [
          '#type' => 'select',
          '#title' => $this->t('Text Hyphenation'),
          '#options' => $hyphenate,
          '#description' => $this->t('If you want to use hyphenation, then you need to download from <a href="@url">ctan.org</a> your needed pattern set. Then upload it to the dir "hyphenate_patterns" in the TCPDF lib directory. Perhaps you need to create the dir first. If you select the automated detection, then we try to get the language of the current node and select an appropriate hyphenation pattern.', ['@url' => 'http://www.ctan.org/tex-archive/language/hyph-utf8/tex/generic/hyph-utf8/patterns/tex']),
          '#default_value' => $this->getOption('default_text_hyphenate'),
        ];

        $form['default_font_color'] = [
          '#type' => 'textfield',
          '#title' => $this->t('Text Color'),
          '#description' => $this->t('If a value is entered without a comma, it will be interpreted as a hexadecimal RGB color. Normal RGB can be used by separating the components by a comma. e.g 255,255,255 for white. A CMYK color can be entered in the same way as RGB. e.g. 0,100,0,0 for magenta.'),
          '#size' => 20,
          '#default_value' => $this->getOption('default_font_color'),
        ];

        break;

      case 'pdf_header':
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

        $form['#title'] .= $this->t('PDF Header & Footer Options');

        $form['header'] = [
          '#type' => 'fieldset',
          '#title' => $this->t('Header options'),
          '#collapsed' => TRUE,
          '#collapsible' => TRUE,
        ];
        $form['header']['header_margin'] = [
          '#type' => 'textfield',
          '#title' => $this->t('Top margin'),
          '#size' => 10,
          '#default_value' => $this->getOption('header_margin'),
        ];
        $form['header']['header_font_size'] = [
          '#type' => 'textfield',
          '#title' => $this->t('Font Size'),
          '#size' => 10,
          '#default_value' => $this->getOption('header_font_size'),
        ];
        $form['header']['header_font_family'] = [
          '#type' => 'select',
          '#title' => $this->t('Font Family'),
          '#options' => $fonts,
          '#size' => 5,
          '#default_value' => $this->getOption('header_font_family'),
        ];
        $form['header']['header_font_style'] = [
          '#type' => 'checkboxes',
          '#title' => $this->t('Font Style'),
          '#options' => $font_styles,
          '#default_value' => $this->getOption('header_font_style'),
        ];
        $form['header']['header_text_align'] = [
          '#type' => 'radios',
          '#title' => $this->t('Text Alignment'),
          '#options' => $align,
          '#default_value' => $this->getOption('header_text_align'),
        ];
        $form['header']['header_font_color'] = [
          '#type' => 'textfield',
          '#title' => $this->t('Text Color'),
          '#description' => $this->t('If a value is entered without a comma, it will be interpreted as a hexadecimal RGB color. Normal RGB can be used by separating the components by a comma. e.g 255,255,255 for white. A CMYK color can be entered in the same way as RGB. e.g. 0,100,0,0 for magenta.'),
          '#size' => 20,
          '#default_value' => $this->getOption('header_font_color'),
        ];

        $form['footer'] = [
          '#type' => 'fieldset',
          '#title' => $this->t('Footer options'),
          '#collapsed' => TRUE,
          '#collapsible' => TRUE,
        ];
        $form['footer']['footer_spacing'] = [
          '#type' => 'textfield',
          '#title' => $this->t('Bottom spacing'),
          '#size' => 10,
          '#default_value' => $this->getOption('footer_spacing'),
        ];
        $form['footer']['footer_font_size'] = [
          '#type' => 'textfield',
          '#title' => $this->t('Font Size'),
          '#size' => 10,
          '#default_value' => $this->getOption('footer_font_size'),
        ];
        $form['footer']['footer_font_family'] = [
          '#type' => 'select',
          '#title' => $this->t('Font Family'),
          '#options' => $fonts,
          '#size' => 5,
          '#default_value' => $this->getOption('footer_font_family'),
        ];
        $form['footer']['footer_font_style'] = [
          '#type' => 'checkboxes',
          '#title' => $this->t('Font Style'),
          '#options' => $font_styles,
          '#default_value' => $this->getOption('footer_font_style'),
        ];
        $form['footer']['footer_text_align'] = [
          '#type' => 'radios',
          '#title' => $this->t('Text Alignment'),
          '#options' => $align,
          '#default_value' => $this->getOption('footer_text_align'),
        ];
        $form['footer']['footer_font_color'] = [
          '#type' => 'textfield',
          '#title' => $this->t('Text Color'),
          '#description' => $this->t('If a value is entered without a comma, it will be interpreted as a hexadecimal RGB color. Normal RGB can be used by separating the components by a comma. e.g 255,255,255 for white. A CMYK color can be entered in the same way as RGB. e.g. 0,100,0,0 for magenta.'),
          '#size' => 20,
          '#default_value' => $this->getOption('footer_font_color'),
        ];

        break;

      case 'pdf_template':
        $form['#title'] .= $this->t('PDF Templates');

        $form['leading'] = [
          '#type' => 'fieldset',
          '#title' => $this->t('Leading PDF Template'),
        ];
        $form['leading']['leading_template'] = [
          '#type' => 'entity_autocomplete',
          '#target_type' => 'views_pdf_template',
          '#selection_handler' => 'default',
          '#description' => $this->t('Here you specify a PDF file to be printed before the main content.'),
          '#default_value' => $this->getOption('leading_template') ? ViewsPdfTemplate::load($this->getOption('leading_template')) : '',
        ];
        $form['leading']['leading_header'] = [
          '#type' => 'checkbox',
          '#title' => 'Print page headers on leading template',
          '#default_value' => $this->getOption('leading_header'),
        ];
        $form['leading']['leading_footer'] = [
          '#type' => 'checkbox',
          '#title' => 'Print page footers on leading template',
          '#default_value' => $this->getOption('leading_footer'),
        ];

        $form['background'] = [
          '#type' => 'fieldset',
          '#title' => $this->t('Background PDF Template'),
        ];
        $form['background']['template'] = [
          '#type' => 'entity_autocomplete',
          '#target_type' => 'views_pdf_template',
          '#selection_handler' => 'default',
          '#description' => $this->t('Here you specify a PDF file on which the content is printed. The first page of this document is used for the first page, in the target document. The second page is used for the second page in the target document and so on. If the target document has more that this template file, the last page of the template will be repeated. The leading document has no effect on the order of the pages.'),
          '#default_value' => $this->getOption('template') ? ViewsPdfTemplate::load($this->getOption('template')) : '',
        ];

        $form['succeed'] = [
          '#type' => 'fieldset',
          '#title' => $this->t('Trailing PDF Template'),
        ];
        $form['succeed']['succeed_template'] = [
          '#type' => 'entity_autocomplete',
          '#target_type' => 'views_pdf_template',
          '#selection_handler' => 'default',
          '#description' => $this->t('Here you specify a PDF file to be printed after the main content.'),
          '#default_value' => $this->getOption('succeed_template') ? ViewsPdfTemplate::load($this->getOption('succeed_template')) : '',
        ];
        $form['succeed']['succeed_header'] = [
          '#type' => 'checkbox',
          '#title' => 'Print page headers on trailing template',
          '#default_value' => $this->getOption('succeed_header'),
        ];
        $form['succeed']['succeed_footer'] = [
          '#type' => 'checkbox',
          '#title' => 'Print page footers on trailing template',
          '#default_value' => $this->getOption('succeed_footer'),
        ];

        $form['notes']['#markup'] =
          $this->t('To manage template files and upload new ones, go to the <a href=":link">PDF Templates</a> tab on the main Views page.', [':link' => Url::fromRoute('entity.views_pdf_template.collection')->toString()]);

        break;

      case 'displays':
        $form['#title'] .= $this->t('Attach to');
        $displays = [];
        foreach ($this->view->display as $display_id => $display) {
          if (!empty($display->handler) && $display->handler->accept_attachments()) {
            $displays[$display_id] = $display->display_title;
          }
        }
        $form['displays'] = [
          '#type' => 'checkboxes',
          '#description' => $this->t('The feed icon will be available only to the selected displays.'),
          '#options' => $displays,
          '#default_value' => $this->getOption('displays'),
        ];
        break;

      case 'css':
        $form['#title'] .= $this->t('CSS File');
        $form['css_file'] = [
          '#type' => 'textfield',
          '#description' => $this->t('URL to a CSS file. This file is attached to all fields, rendered as HTML.'),
          '#default_value' => $this->getOption('css_file'),
        ];
        break;
    }
  }
  /**
   * {@inheritdoc}
   */
  public function submitOptionsForm(&$form, FormStateInterface $form_state) {
    parent::submitOptionsForm($form, $form_state);

    switch ($form_state->get('section')) {
      case 'menu':
        $menu = $form_state->getValue('menu');
        [$menu['menu_name'], $menu['parent']] = explode(':', $menu['parent'], 2);
        $this->setOption('menu', $menu);
        // send ajax form to options page if we use it.
        if ($form_state->getValue(['menu', 'type']) == 'default tab') {
          $form_state->get('view')->addFormToStack('display', $this->display['id'], 'tab_options');
        }
        break;

      case 'tab_options':
        $this->setOption('tab_options', $form_state->getValue('tab_options'));
        break;

      case 'pdf_page':
        $this->setOption('default_page_format', $form_state->getValue('default_page_format'));
        $this->setOption('default_page_format_custom', $form_state->getValue('default_page_format_custom'));
        $this->setOption('default_page_orientation', $form_state->getValue('default_page_orientation'));
        $this->setOption('unit', $form_state->getValue('unit'));
        $this->setOption('margin_left', $form_state->getValue('margin_left'));
        $this->setOption('margin_right', $form_state->getValue('margin_right'));
        $this->setOption('margin_top', $form_state->getValue('margin_top'));
        $this->setOption('margin_bottom', $form_state->getValue('margin_bottom'));

        break;

      case 'pdf_fonts':
        $this->setOption('default_font_size', $form_state->getValue('default_font_size'));
        $this->setOption('right_to_left', $form_state->getValue('right_to_left'));
        $this->setOption('default_font_style', $form_state->getValue('default_font_style'));
        $this->setOption('default_font_family', $form_state->getValue('default_font_family'));
        $this->setOption('default_text_align', $form_state->getValue('default_text_align'));
        $this->setOption('default_font_color', $form_state->getValue('default_font_color'));

        break;

      case 'pdf_header':
        $this->setOption('header_margin', $form_state->getValue('header_margin'));
        $this->setOption('header_font_size', $form_state->getValue('header_font_size'));
        $this->setOption('header_font_style', $form_state->getValue('header_font_style'));
        $this->setOption('header_font_family', $form_state->getValue('header_font_family'));
        $this->setOption('header_text_align', $form_state->getValue('header_text_align'));
        $this->setOption('header_font_color', $form_state->getValue('header_font_color'));

        $this->setOption('footer_spacing', $form_state->getValue('footer_spacing'));
        $this->setOption('footer_font_size', $form_state->getValue('footer_font_size'));
        $this->setOption('footer_font_style', $form_state->getValue('footer_font_style'));
        $this->setOption('footer_font_family', $form_state->getValue('footer_font_family'));
        $this->setOption('footer_text_align', $form_state->getValue('footer_text_align'));
        $this->setOption('footer_font_color', $form_state->getValue('footer_font_color'));

        break;

      case 'pdf_template':

        $this->setOption('leading_template', $form_state->getValue('leading_template'));
        $this->setOption('leading_header', $form_state->getValue('leading_header'));
        $this->setOption('leading_footer', $form_state->getValue('leading_footer'));
        $this->setOption('template', $form_state->getValue('template'));
        $this->setOption('succeed_template', $form_state->getValue('succeed_template'));
        $this->setOption('succeed_header', $form_state->getValue('succeed_header'));
        $this->setOption('succeed_footer', $form_state->getValue('succeed_footer'));
        break;

      case 'displays':
        $this->setOption('displays', $form_state->getValue('displays'));
        break;

      case 'css':
        $this->setOption('css_file', $form_state->getValue('css_file'));
        break;

    }
  }

}
