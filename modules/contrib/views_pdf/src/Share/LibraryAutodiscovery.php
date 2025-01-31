<?php
declare(strict_types=1);

namespace Drupal\views_pdf\Share;

/**
 * Service Class
 */
class LibraryAutodiscovery {

  public const SUPPORTED_LIBRARIES = [
    'fpdi' => [ 'version' => 235 ],
    'tcpdf' => [ 'version' => 642 ],
    'fpdf' => [ 'version' => 184 ],
    'tc-lib-pdf' => [ 'version' =>  8011 ]
  ];

  /**
   * @return array
   */
  public static function find() : array {
    $foundLibraries = [];

    if (class_exists(\FPDF::class) && class_exists(\setasign\Fpdi\Fpdi::class)) {
      $foundLibraries['fpdi'] = [
        'version' => \setasign\Fpdi\Fpdi::VERSION,
        'version_control' => self::cleanVersion(\setasign\Fpdi\Fpdi::VERSION)
      ];
      $foundLibraries['fpdf'] = [
        'version' => \FPDF_VERSION,
        'version_control' => self::cleanVersion(\FPDF_VERSION)
      ];
    }

    if (class_exists(\TCPDF::class) && class_exists(\setasign\Fpdi\Tcpdf\Fpdi::class)) {
      $foundLibraries['fpdi'] = [
        'version' => \setasign\Fpdi\Tcpdf\Fpdi::VERSION,
        'version_control' => self::cleanVersion(\setasign\Fpdi\Tcpdf\Fpdi::VERSION)
      ];
      $foundLibraries['tcpdf'] = [
        'version' => \TCPDF_STATIC::getTCPDFVersion(),
        'version_control' => self::cleanVersion(\TCPDF_STATIC::getTCPDFVersion())
      ];
    }

    if (class_exists(\Com\Tecnick\Pdf\Tcpdf::class)) {
      $foundLibraries['tc-lib-pdf'] = [
        'version' => (\Com\Tecnick\Pdf\Tcpdf)->getVersion(),
        'version_control' => self::cleanVersion((\Com\Tecnick\Pdf\Tcpdf)->getVersion())
      ];
    }

    return $foundLibraries;
  }

  /**
   * Helper to compare supported version.
   *
   * @param string $version
   *
   * @return int
   */
  protected static function cleanVersion(string $version) : int {
    return (int) str_replace('.', '', $version);
  }

}
