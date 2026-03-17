<?php

require_once 'advancedtokens.civix.php';

use CRM_Advancedtokens_ExtensionUtil as E;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\DependencyInjection\ContainerBuilder;

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

/**
 * Add token services to the container.
 *
 * @param \Symfony\Component\DependencyInjection\ContainerBuilder $container
 */
function advancedtokens_civicrm_container(ContainerBuilder $container) {
  $container->addResource(new FileResource(__FILE__));
  $container->findDefinition('dispatcher')->addMethodCall('addListener',
    ['civi.token.list', 'advancedtokens_register_tokens']
  )->setPublic(TRUE);
  $container->findDefinition('dispatcher')->addMethodCall('addListener',
    ['civi.token.eval', 'advancedtokens_evaluate_tokens']
  )->setPublic(TRUE);
}

function advancedtokens_register_tokens(\Civi\Token\Event\TokenRegisterEvent $e) {
  $e->entity('date')
    ->register('currentDateFull', 'Aktuelles Datum');
  $e->entity('activity')
    ->register('contributionIncrease', 'Letzte Beitragserhöhung');
  $e->entity('membership')
    ->register('startDate', 'Beginn der aktuellen Mitgliedschaft');
  $e->entity('membership')
    ->register('endDate', 'Ablaufdatum der aktuellen Mitgliedschaft');
  $e->entity('membership')
    ->register('paymentMethod', 'Zahlart der aktuellen Mitgliedschaft');
  $e->entity('membership')
    ->register('amount', 'Betrag der aktuellen Mitgliedschaft');
  $e->entity('membership')
    ->register('paymentRhythm', 'Turnus der aktuellen Mitgliedschaft');
}

function advancedtokens_evaluate_tokens(\Civi\Token\Event\TokenValueEvent $e) {
  $contactIds = [];

  foreach ($e->getRows() as $row) {
    $contactIds[] = $row->context['contactId'];
  }

  if (empty($contactIds)) {
    return;
  }

  // Get requested message tokens
  $messageTokens = $e->getTokenProcessor()->getMessageTokens();

  // Get date formatter
  $formatter = new \IntlDateFormatter('de_DE', \IntlDateFormatter::LONG, \IntlDateFormatter::NONE);
  
  // DATE TOKENS
  if (!empty($messageTokens['date']) && in_array('currentDateFull', $messageTokens['date'])) {
    // Retrieve current date
    $currentDate = new \DateTime();
    $formattedCurrentDate = $formatter->format($currentDate);
    
    foreach ($e->getRows() as $row) {
      $row->tokens('date', 'currentDateFull', $formattedCurrentDate);
    }
  }

  // ACTIVITY TOKENS
  if (!empty($messageTokens['activity']) && in_array('contributionIncrease', $messageTokens['activity'])) {
    foreach ($e->getRows() as $row) {
      $contactId = $row->context['contactId'];
      try {
        $activities = civicrm_api3('Activity', 'get', [
          'target_contact_id' => $contactId,
          'activity_type_id' => 'Beitragserhöhung',
          'sequential' => 1,
          'option.limit' => 1,
          'sort' => 'activity_date_time DESC',
        ]);

        if (!empty($activities['values'][0])) {
          $contributionIncrease = $activities['values'][0]['custom_114'] ?? '';

          $formattedContributionIncrease = $contributionIncrease !== '' ? number_format((float)$contributionIncrease, 2, ',', '.') : '';

          $row->tokens('activity', 'contributionIncrease', $formattedContributionIncrease);
        }

      } catch (\CiviCRM_API3_Exception $e) {
        \Civi::log()->warning("Error fetching activity for contact $contactId: " . $e->getMessage());
      }
    }
  }

  // MEMBERSHIP TOKENS
  if (!empty($messageTokens['membership'])) {
    // Get option values for custom fields
    try {
      $paymentMethodOptions = civicrm_api3('OptionValue', 'get', [
        'option_group_id' => 'Weitere_Informationen_Zahlart',
        'sequential' => 1,
      ]);

      $paymentMethods = array_column($paymentMethodOptions['values'], 'label', 'value');
    } catch (\CiviCRM_API3_Exception $e) {
      $paymentMethods = [];
    }

    try {
      $paymentRhythmOptions = civicrm_api3('OptionValue', 'get', [
        'option_group_id' => 'Weitere_Informationen_Turnus',
        'sequential' => 1,
      ]);

      $paymentRhythms = array_column($paymentRhythmOptions['values'], 'label', 'value');
    } catch (\CiviCRM_API3_Exception $e) {
      $paymentRhythms = [];
    }

    foreach ($e->getRows() as $row) {
      // Get the latest membership for the contact
      $contactId = $row->context['contactId'];
      
      try {
        $memberships = civicrm_api3('Membership', 'get', [
          'contact_id' => $contactId,
          'sequential' => 1,
          'status_id' => ['IN' => ['New', 'Current']],
          'option.limit' => 1,
          'sort' => 'start_date DESC',
        ]);
      } catch (\CiviCRM_API3_Exception $e) {
        $memberships = ['values' => []];
      }
        
      if (isset($memberships['values'][0])) {
        $membership = $memberships['values'][0];

        // Retrieve start date
        $startDate = $membership["start_date"] ?? null;
        if ($startDate) {
          $dateTimeStartDate = DateTime::createFromFormat('Y-m-d', $startDate);
          if ($dateTimeStartDate) {
            $formattedStartDate = $formatter->format($dateTimeStartDate);
          } else {
            $formattedStartDate = ''; 
          }
        } else {
          $formattedStartDate = '';
        }

        // Retrieve end date
        $endDate = $membership["end_date"] ?? null;
        if ($endDate) {
          $dateTimeEndDate = DateTime::createFromFormat('Y-m-d', $endDate);
          if ($dateTimeEndDate) {
            $formattedEndDate = $formatter->format($dateTimeEndDate);
          } else {
            $formattedEndDate = ''; 
          }
        } else {
          $formattedEndDate = '';
        }

        // Retrieve payment method
        $paymentMethodLabel = '';
        $paymentMethodId = $membership['custom_111_1'] ?? null ;

        if ($paymentMethodId) {
          $paymentMethodLabel = $paymentMethods[$paymentMethodId] ?? '';
        }

        // Retrieve amount
        $amount = $membership['custom_112_1'] ?? '';

        // Retrieve payment rhythm
        $paymentRhythmLabel = '';
        $paymentRhythmId = $membership['custom_113_1'] ?? null;
        if ($paymentRhythmId) {
          $paymentRhythmLabel = $paymentRhythms[$paymentRhythmId] ?? '';
        }
        
        // Add membership tokens
        $row->tokens('membership', 'startDate', $formattedStartDate);
        $row->tokens('membership', 'endDate', $formattedEndDate);
        $row->tokens('membership', 'paymentMethod', $paymentMethodLabel);
        $row->tokens('membership', 'amount', $amount);
        $row->tokens('membership', 'paymentRhythm', $paymentRhythmLabel);
      } 
    }
  }
}

