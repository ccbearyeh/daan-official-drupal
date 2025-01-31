<?php

namespace Drupal\Tests\contribute\Functional;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Tests\BrowserTestBase;

/**
 * Contribute browser test.
 *
 * @group contribute
 */
class ContributeFunctionalTest extends BrowserTestBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['contribute'];

  /**
   * Test get.
   */
  public function testStatusReport() {
    $this->drupalLogin($this->rootUser);

    // Check that the 'Status report' includes 'Community information'.
    $this->drupalGet('/admin/reports/status');
    $this->assertSession()->responseContains('Community information');
    $this->assertSession()->responseContains('When you <a href="https://register.drupal.org/user/register">create a Drupal.org account</a>, you gain access to a whole ecosystem of Drupal.org sites and services.');

    // Check that the 'Status report' includes jrockowitz's user information.
    $edit = [
      'account_type' => 'user',
      'user_id' => 'jrockowitz',
    ];
    $this->drupalGet('/admin/reports/status/contribute/configure');
    $this->submitForm($edit, $this->t('Save'));
    $this->assertSession()->responseContains('Community information has been saved.');
    $this->assertSession()->responseContains('Community information');
    $this->assertSession()->responseNotContains('When you <a href="https://register.drupal.org/user/register">create a Drupal.org account</a>, you gain access to a whole ecosystem of Drupal.org sites and services.');
    $this->assertSession()->responseContains('<strong><a href="https://www.drupal.org/u/jrockowitz">Jacob Rockowitz</a></strong>');
    $this->drupalGet('/admin/reports/status/contribute/configure');

    // Check that 'Community information' can be cleared.
    $this->submitForm([], $this->t('Clear'));
    $this->assertSession()->responseContains('Community information has been cleared.');
    $this->assertSession()->responseNotContains('<strong><a href="https://www.drupal.org/u/jrockowitz">Jacob Rockowitz</a></strong>');

    // Check that 'Community information' can be disabled.
    $edit = ['disable' => TRUE];
    $this->drupalGet('/admin/reports/status/contribute/configure');
    $this->submitForm($edit, $this->t('Save'));
    $this->assertSession()->responseContains('Community information has been disabled.');

    // Check that 'Community information' can be remove.
    $this->drupalGet('/admin/reports/status');
    $this->assertSession()->responseNotContains('Community information');
  }

}
