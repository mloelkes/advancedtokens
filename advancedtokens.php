<?php

require_once 'advancedtokens.civix.php';

use CRM_Advancedtokens_ExtensionUtil as E;

/**
 * Implements hook_civicrm_config().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_config/
 */
function advancedtokens_civicrm_config(&$config): void {
  _advancedtokens_civix_civicrm_config($config);
}

/**
 * Implements hook_civicrm_install().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_install
 */
function advancedtokens_civicrm_install(): void {
  _advancedtokens_civix_civicrm_install();
}

/**
 * Implements hook_civicrm_enable().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_enable
 */
function advancedtokens_civicrm_enable(): void {
  _advancedtokens_civix_civicrm_enable();
}
