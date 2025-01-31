<?php

/**
 * @file
 * Hook views data.
 */

/**
 * Implements hook_views_data().
 */
function fpdi_print_views_data() {
  $data['views']['area_fpdi_print_views'] = [
    'title' => t('Print'),
    'help' => t('Pdf print'),
    'area' => [
      'id' => 'fpdi_print_views_link',
    ],
  ];
  return $data;
}
