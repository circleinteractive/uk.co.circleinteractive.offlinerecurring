<?php
/*
 * CiviCRM Offline Recurring Payment Extension for CiviCRM - Circle Interactive 2013
 * Original author: rajesh
 * http://sourceforge.net/projects/civicrmoffline/
 * Converted to Civi extension by andyw@circle, 07/01/2013
 *
 * Distributed under the GNU Affero General Public License, version 3
 * http://www.gnu.org/licenses/agpl-3.0.html
 *
 */
class CRM_OfflineRecurring_Form_RecurringContribution extends CRM_Core_Form {
  /**
   * The id of the contribution that we are processing.
   *
   * @var int
   */
  public $_id;
  /**
  * The id of the contact associated with this contribution.
  *
  * @var int
  */
  public $_contactID;

  /**
  * The contribution recur values if an existing contribution recur
  */
  public $_values = [];

  /**
   * Explicitly declare the form context.
   */
  public function getDefaultContext() {
    return 'create';
  }
  /**
  * build all the data structures needed to build the form
  *
  * @return void
  * @access public
  */
  public function preProcess() {
    parent::preProcess();
    if (($this->_action & CRM_Core_Action::UPDATE)
      && !CRM_Core_Permission::check('edit offline recurring payments')
    ) {
      CRM_Core_Error::fatal(ts('You do not have permission to access this page.'));
    }
    elseif (($this->_action & CRM_Core_Action::ADD)
      && !CRM_Core_Permission::check('add offline recurring payments')
    ) {
      CRM_Core_Error::fatal(ts('You do not have permission to access this page.'));
    }

    $this->_contactID = CRM_Utils_Request::retrieve('cid', 'Integer', $this);
    $this->_id = CRM_Utils_Request::retrieve('crid', 'Integer', $this);
    try {
      $displayName = civicrm_api3('contact', 'getvalue', [
        'id' => $this->_contactID,
        'return' => 'display_name',
      ]);
      if ($this->_id) {
        $this->_values = civicrm_api3('ContributionRecur', 'getsingle', ['id' => $this->_id]);
        $this->assign('recur_id', $this->_id);
        if (CRM_Utils_Array::value('enable_edit', $this->_values) === FALSE) {
          CRM_Core_Error::fatal(ts('You are not allowed to edit the recurring record.'));
        }
      }
    }
    catch (CiviCRM_API3_Exception $e) {
      CRM_Core_Error::fatal(ts("Contact or Contribution Recur doesn't exists."));
    }
    CRM_Utils_System::setTitle(ts('Setup Recurring Payment - ') . $displayName);
  }

  /**
   * Set default values.
   *
   * @return array
   */
  public function setDefaultValues() {
    $defaults = $this->_values;
    $defaults['recur_id'] = $this->_id;
    if (empty($defaults['payment_instrument_id'])) {
      $defaults['payment_instrument_id'] = key(CRM_Core_OptionGroup::values('payment_instrument', FALSE, FALSE, FALSE, 'AND is_default = 1'));
    }
    return $defaults;
  }

  /**
  * Build the form
  *
  * @access public
  * @return void
  */
  public function buildQuickForm() {
    $attributes = CRM_Core_DAO::getAttribute('CRM_Contribute_DAO_ContributionRecur');
    if ($this->_action & CRM_Core_Action::UPDATE) {
      $this->addElement('hidden', 'recur_id');
      $this->addEntityRef(
        'contact_id',
        ts('Contact'),
        ['create' => TRUE, 'api' => ['extra' => ['email']]],
        TRUE
      );
      $this->addElement('checkbox', 'move_recurring_record', ts('Move Recurring Record?'));
      $this->addElement('checkbox', 'move_existing_contributions', ts('Move Existing Contributions?'));
    }
    $this->addMoney('amount',
      ts('Amount'),
      TRUE,
      $attributes['amount'],
      TRUE, 'currency', NULL, TRUE
    );
    $this->_values['is_recur_interval'] = 1;
    $this->_values['recur_frequency_unit'] = implode(
      CRM_Core_DAO::VALUE_SEPARATOR,
      CRM_Core_OptionGroup::values('recur_frequency_units')
    );

    CRM_Contribute_Form_Contribution_Main::buildRecur($this);
    foreach ([
      'start_date' => TRUE,
      'next_sched_contribution_date' => TRUE,
      'end_date' => FALSE,
    ] as $field => $isRequired) {
      $this->addField($field, ['entity' => 'ContributionRecur'], $isRequired, FALSE);
    }

    $this->addEntityRef('financial_type_id', ts('Financial Type'), ['entity' => 'FinancialType'], TRUE);
    $this->add('select', 'payment_instrument_id',
      ts('Payment Method'),
      ['' => ts('- select -')] + CRM_Contribute_PseudoConstant::paymentInstrument(),
      TRUE
    );
    $this->addButtons([
      [
        'type' => 'next',
        'name' => ts('Save'),
        'spacing' => '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;',
        'isDefault' => TRUE,
      ],
      [
        'type' => 'cancel',
        'name' => ts('Cancel'),
        'spacing' => '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;',
      ]
    ]);
    $this->addFormRule(['CRM_OfflineRecurring_Form_RecurringContribution', 'formRule'], $this);
  }

  /**
  * global validation rules for the form
  *
  * @param array $fields posted values of the form
  *
  * @return array list of errors to be posted back to the form
  * @static
  * @access public
  */
  public static function formRule($fields, $files, $self) {
    $errors = [];
    if (!empty($fields['start_date']) && !empty($fields['end_date'])) {
      $start = CRM_Utils_Date::processDate($fields['start_date']);
      $end = CRM_Utils_Date::processDate($fields['end_date']);
      if (($end < $start) && ($end != 0)) {
        $errors['end_date'] = ts('End date should be after Start date');
      }
    }
    return $errors;
  }

  /**
  * process the form after the input has been submitted and validated
  *
  * @access public
  * @return None
  */
  public function postProcess() {
    $params = $this->controller->exportValues();
    $this->submit($params);
  }

  /**
  * Submit function.
  *
  * This is the guts of the postProcess made also accessible to the test suite.
  *
  * @param array $params
  *   Submitted values.
  */
  public function submit($params) {
    $params['recur_id'] = $this->_id;
    $hash = md5(uniqid(rand(), TRUE));
    $recurParams = [
      'contact_id' => $this->_contactID,
      'amount' => $params['amount'],
      'currency' => $params['currency'],
      'frequency_unit' => $params['frequency_unit'],
      'frequency_interval' => $params['frequency_interval'],
      'installments' => $params['installments'],
      'start_date' => $params['start_date'],
      'create_date' => date('Ymd'),
      'end_date' => $params['end_date'],
      'trxn_id' => $hash,
      'invoice_id' => $hash,
      'contribution_status_id' => 'In Progress',
      'next_sched_contribution_date' => $params['next_sched_contribution_date'],
      'financial_type_id' => $params['financial_type_id'],
      'payment_instrument_id' => $params['payment_instrument_id'],
    ];
    if (!empty($this->_id)) {
      $recurParams['id'] = $this->_id;
    }
    foreach (['start_date','end_date','next_sched_contribution_date',] as $date) {
      if (!empty($recurParams[$date])) {
        $recurParams[$date] = CRM_Utils_Date::processDate($recurParams[$date]);
      }
    }

    if ($this->_action & CRM_Core_Action::UPDATE) {
      // Moving recurring record to another contact, if 'Move Recurring Record?' is ticked
      if (!empty($params['move_recurring_record'])) {
        $recurParams['contact_id'] = $params['contact_id'];
        if (!empty($params['move_existing_contributions'])) {
          // Update contact id in civicrm_contribution table, if 'Move Existing Contributions?' is ticked
          if ($recurParams['contact_id'] != $this->_contactID) {
            $contributions = civicrm_api3('Contribution', 'get', ['contribution_recur_id' => $this->_id, 'return' => 'id']);
            foreach ($contributions['values'] as $contribution) {
              try {
                civicrm_api3('Contribution', 'create', ['id' => $contribution['id'], 'contact_id' => $recurParams['contact_id']]);
              }
              catch (CiviCRM_API3_Exception $e) {
                CRM_Core_Error::fatal(ts("Contribution cannot be updated."));
              }
            }
          }
        }
      }
    }
    $recurring = civicrm_api3('ContributionRecur', 'create', $recurParams);
    CRM_OfflineRecurring_BAO_RecurringContribution::add($recurring['id']);
  }

}
