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

/**
 * This class provides the functionality to delete a group of
 * contacts. This class provides functionality for the actual
 * addition of contacts to groups.
 */

class Recurring_Form_RecurringContribution extends CRM_Core_Form {

    /**
     * build all the data structures needed to build the form
     *
     * @return void
     * @access public
     */
	function preProcess() {	
        parent::preProcess( );
	}
	
    /**
     * Build the form
     *
     * @access public
     * @return void
     */
    function buildQuickForm( ) {
      
		$attributes = CRM_Core_DAO::getAttribute('CRM_Contribute_DAO_ContributionRecur');
	    $action     = @$_GET['action'];
        $cid        = CRM_Utils_Request::retrieve('cid', 'Integer', $this);
        $id         = CRM_Utils_Request::retrieve('id', 'Integer', $this);
        
        require_once 'api/api.php';
        $result = civicrm_api('contact', 'get',
            array(
                'version' => 3,
                'id'      => $cid
            )
        );
        if ($result['is_error']) {
            CRM_Core_Error::fatal('Unable to query contact id in ' . __FILE__ . ' at line ' . __LINE__);
        } else {
            $contact_details = reset($result['values']);
        }
        
        CRM_Utils_System::setTitle('Setup Recurring Payment - ' . $contact_details['display_name']);

        if ($action == 'update') {
          
          // Check permission to edit recurring record
          if (!CRM_Core_Permission::check('edit offline recurring payments')) {
            CRM_Utils_System::permissionDenied();
          }

    		$dao = CRM_Core_DAO::executeQuery(
                "SELECT * FROM civicrm_contribution_recur WHERE id = %1",
                array(1 => array($id, 'Integer')) 
            );
            
            if ($dao->fetch()) {
                
                if (_offlinerecurring_getCRMVersion() >= 4.4)
                    $dao->next_sched_contribution = $dao->next_sched_contribution_date;

                $defaults = array(
                    'amount'=>$dao->amount ,
                    'frequency_interval'      => $dao->frequency_interval,
                    'frequency_unit'          => $dao->frequency_unit,
                    'start_date'              => $dao->start_date,
                    'processor_id'            => $dao->processor_id,
                    'next_sched_contribution' => $dao->next_sched_contribution,
                    'end_date'                => $dao->end_date,
                    'recur_id'                => $dao->id,
                    'payment_processor_id'    => $dao->payment_processor_id,
                    'payment_instrument_id'   => $dao->payment_instrument_id,  
                    'enable_edit'             => 0,
                    //'standard_price'=>$dao->standard_price ,
                    //'vat_rate'=>$dao->vat_rate 
               );
                
                // Allow $defaults to be modified via hook, before edit form displayed
                // This will allow 'edit' to enabled for certain 'payment instruments' or 'payment processor'
                // 'edit' is disabled for all recurring contributions by default
                require_once 'Recurring/Utils/Hook.php';
                Recurring_Utils_Hook::alterRecurringContributionParams( $defaults );
                
                // Redirect if 'edit' is disabled in hook
                if ($defaults['enable_edit'] == 0) {
                  $session = CRM_Core_Session::singleton();
                  $status = ts('You are not allowed to edit the recurring record.');
                  CRM_Core_Session::setStatus($status);  
                  CRM_Utils_System::redirect(
                      CRM_Utils_System::url('civicrm/contact/view', "reset=1&cid=$cid&force=1&selectedChild=contribute")
                  );
                }
                             
               if (CRM_Utils_Array::value('start_date', $defaults) && !empty($dao->start_date) && $dao->start_date != '0000-00-00') {
                   list($defaults['start_date'], $defaults['start_date_time']) 
                        = CRM_Utils_Date::setDateDefaults($defaults['start_date'], 'activityDate');    
               } else {
                   $defaults['start_date'] = "";     
               }                       
               if (CRM_Utils_Array::value( 'next_sched_contribution', $defaults) && !empty($dao->next_sched_contribution) && $dao->next_sched_contribution != '0000-00-00') {
                   list($defaults['next_sched_contribution'], $defaults['next_sched_contribution_time']) 
                        = CRM_Utils_Date::setDateDefaults($defaults['next_sched_contribution'], 'activityDate');    
               } else {
                   $defaults['next_sched_contribution'] = "";     
               }
               if (CRM_Utils_Array::value('end_date', $defaults) && !empty($dao->start_date) && $dao->start_date != '0000-00-00') {
                   list($defaults['end_date'], $defaults['end_date_time']) 
                        = CRM_Utils_Date::setDateDefaults($defaults['end_date'], 'activityDate');    
               } else {
                   $defaults['end_date'] = "";     
               }  
            }
            $this->addElement('hidden', 'recur_id', $id);
            $this->assign('recur_id', $id);
            
            $this->addElement('text', 'contact_name', 'Contact', array('size' => 50, 'maxlength' => 255));
            $this->addElement('hidden', 'selected_cid', 'selected_cid');
            $this->addElement('checkbox', 'move_recurring_record', ts('Move Recurring Record?'));
            $this->addElement('checkbox', 'move_existing_contributions', ts('Move Existing Contributions?'));
            // Set move existing contributions to TRUE as default
            $defaults['move_existing_contributions'] = 1;
            $defaults['contact_name'] = $contact_details['sort_name'];
            $defaults['selected_cid'] = $cid;
            
            // Check if membership is linked with the recur record and allowed to be moved to different membership
            // NOTE: 'membership_id' is not in 'civicrm_contribution_recur' table by default
            if(CRM_Core_DAO::checkFieldExists('civicrm_contribution_recur', 'membership_id')) {
              // Get memberships of the contact
              // This will allow the recur record to be attached to a different membership of the same contact
              $memberships = civicrm_api('membership', 'get',  array('version' => 3, 'contact_id' => $cid));
              $existingMemberships = array('0' => '- select -');
              if (!empty($memberships['values'])) {
                foreach ($memberships['values'] as $membership_id => $membership_details) {
                  $membershipStatusResult = civicrm_api('MembershipStatus', 'getsingle', array('version' => 3, 'id' => $membership_details['status_id']));
                  $existingMemberships[$membership_id] = $membership_details['membership_name']
                      .' / '.$membershipStatusResult['label']
                      .' / '.$membership_details['start_date']
                      .' / '.$membership_details['end_date'];
                }
              }
              $this->add('select', 'membership_record', ts('Membership'), $existingMemberships, FALSE);
              $this->assign('show_move_membership_field', 1);
              $defaults['membership_record'] = $dao->membership_id;
            }
        } else if ($action == 'add') {
          // Check permission to add recurring record
          if (!CRM_Core_Permission::check('add offline recurring payments')) {
            CRM_Utils_System::permissionDenied();
          }
        }
        
        $this->add('text', 'amount', ts('Amount'), array(), true);
    	$this->add('text', 'frequency_interval', ts('Every'), array('maxlength' => 2, 'size' => 2), true);
        //$form->addRule( 'frequency_interval', 
        //                        ts( 'Frequency must be a whole number (EXAMPLE: Every 3 months).' ), 'integer' );
                        
        $frUnits  = implode(CRM_Core_DAO::VALUE_SEPARATOR, CRM_Core_OptionGroup::values('recur_frequency_units'));                    
        $units    = array();
        $unitVals = explode(CRM_Core_DAO::VALUE_SEPARATOR, $frUnits);
        
        $frequencyUnits = CRM_Core_OptionGroup::values('recur_frequency_units');
        
        foreach ($unitVals as $key => $val) {
            if (isset($frequencyUnits[$val])) {
                $units[$val] = $frequencyUnits[$val];
            }
        }

        $frequencyUnit = &$this->add('select', 'frequency_unit', null, $units, true);
        
        // FIXME: Ideally we should freeze select box if there is only
        // one option but looks there is some problem /w QF freeze.
        //if ( count( $units ) == 1 ) {
        //$frequencyUnit->freeze( );
        //}
        
        //$this->add( 'text', 'installments', ts( 'installments' ), $attributes['installments'] );
                                            
        $this->addDate('start_date', ts('Start Date'), true, array('formatType' => 'activityDate'));
        $this->addDate('next_sched_contribution', ts('Next Scheduled Date'), true, array( 'formatType' => 'activityDate'));
        $this->addDate('end_date', ts('End Date'), false, array('formatType' => 'activityDate'));
        
        if (isset($defaults))        
            $this->setDefaults($defaults);
        
        $this->addElement('hidden', 'action', $action);
        $this->addElement('hidden', 'cid', $cid);
        
        $this->assign('cid', $cid);
        
        //$this->addFormRule( array( 'CRM_Package_Form_Package', 'formRule' ) );
                               
        $this->addButtons(
            array( 
                array(
                    'type'      => 'next', 
                    'name'      => ts('Save'), 
                    'spacing'   => '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;', 
                    'isDefault' => true   
                ), 
            ) 
        );
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
    static function formRule( $values ) 
    {
        $errors = array( );

        if (!empty($values['start_date']) && !empty($values['end_date']) ) {
            $start = CRM_Utils_Date::processDate( $values['start_date'] );
            $end   = CRM_Utils_Date::processDate( $values['end_date'] );
            if ( ($end < $start) && ($end != 0) ) {
                $errors['end_date'] = ts( 'End date should be after Start date' );
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
        
        $config =& CRM_Core_Config::singleton();
		$params = $this->controller->exportValues();
        //$params['recur_id'] = $this->get('id');
        $params['recur_id'] = $this->_submitValues['recur_id'];

		if(!empty($params['start_date']))
		    $start_date = CRM_Utils_Date::processDate($params['start_date']);
		if(!empty($params['end_date']))
		    $end_date = CRM_Utils_Date::processDate($params['end_date']);
		if(!empty($params['next_sched_contribution']))    
		    $next_sched_contribution = CRM_Utils_Date::processDate($params['next_sched_contribution']);
    		
        if ($params['action'] == 'add') {

            $fields = "id, contact_id, amount, frequency_interval, frequency_unit, invoice_id, trxn_id, currency, create_date, start_date, next_sched_contribution";
            if (_offlinerecurring_getCRMVersion() >= 4.4)
                $fields .= '_date';

            $values       = "NULL, %1, %2, %3, %4, %5, %6, %7, %8, %9, %10";
            $invoice_id   = md5(uniqid(rand(), true));

            $recur_params = array(
                1 =>  array($params['cid'],                'Integer'),  
                2 =>  array($params['amount'],             'String'),
                3 =>  array($params['frequency_interval'], 'String'),
                4 =>  array($params['frequency_unit'],     'String'),
                5 =>  array($invoice_id,                   'String'),
                6 =>  array($invoice_id,                   'String'),
                7 =>  array($config->defaultCurrency,      'String'),
                8 =>  array(date('YmdHis'),                'String'),
                9 =>  array($start_date,                   'String'),
                10 => array($next_sched_contribution,      'String')
            );

            if (isset($end_date)) {
                $fields          .= ", end_date";
                $values          .= ", %11";
                $recur_params[11] = array($end_date, 'String');
            }

            $sql    = sprintf("INSERT INTO civicrm_contribution_recur (%s) VALUES (%s)", $fields, $values);
            $status = ts('Recurring Contribution setup successfully');        
        
        } elseif ($params['action'] == 'update') {
            
            $sql = "UPDATE civicrm_contribution_recur SET amount = %1, frequency_interval = %2, frequency_unit = %3, start_date = %4, next_sched_contribution = %5, modified_date = %6"; 
            if (_offlinerecurring_getCRMVersion() >= 4.4)
                $sql = str_replace('next_sched_contribution', 'next_sched_contribution_date', $sql);

            $recur_params = array(
                1 =>  array($params['amount'],             'String'),
                2 =>  array($params['frequency_interval'], 'String'),
                3 =>  array($params['frequency_unit'],     'String'),
                4 =>  array($start_date,                   'String'),   
                5 =>  array($next_sched_contribution,      'String'),
                6 =>  array(date('YmdHis'),                'String'),
                7 =>  array($params['recur_id'],           'Integer')
            );

            if (isset($end_date)) {
                $sql            .= ", end_date = %8";
                $recur_params[8] = array($end_date, 'String');
            }

            $sql   .= ' WHERE id = %7';                         
            $status = ts('Recurring Contribution updated');
            
            // Moving recurring record to another contact, if 'Move Recurring Record?' is ticked
            $move_recurring_record = $this->_submitValues['move_recurring_record'];
            if ($move_recurring_record == 1) {
              $move_existing_contributions = $this->_submitValues['move_existing_contributions'];
              $selected_cid = $this->_submitValues['selected_cid'];
              
              if (!empty($selected_cid)) {
                // Update contact id in civicrm_contribution_recur table
                $update_recur_sql = "UPDATE civicrm_contribution_recur SET contact_id = %1 WHERE id = %2";
                $update_recur_params = array(
                  1 =>  array($selected_cid,      'Integer'),
                  2 =>  array($params['recur_id'],  'Integer')
                );
                CRM_Core_DAO::executeQuery($update_recur_sql, $update_recur_params);
                
                // Update contact id in civicrm_contribution table, if 'Move Existing Contributions?' is ticked
                if ($move_existing_contributions == 1) {
                  $update_contribution_sql = "UPDATE civicrm_contribution SET contact_id = %1 WHERE contribution_recur_id = %2";
                  CRM_Core_DAO::executeQuery($update_contribution_sql, $update_recur_params);
                }
                
                // Move recurring record to another membership
                $membership_record = $this->_submitValues['membership_record'];
                if (CRM_Core_DAO::checkFieldExists('civicrm_contribution_recur', 'membership_id')) {
                  // Update membership id in civicrm_contribution_recur table
                  $update_membership_sql = "UPDATE civicrm_contribution_recur SET membership_id = %1 WHERE id = %2";
                  $update_membership_params = array(
                    1 =>  array($membership_record,   'Integer'),
                    2 =>  array($params['recur_id'],  'Integer')
                  );
                  CRM_Core_DAO::executeQuery($update_membership_sql, $update_membership_params);

                  // Move membership payments if 'Move Existing Contributions?' is ticked
                  if ($move_existing_contributions == 1 && $membership_record > 0 ) {
                    
                    // Create/Update membership payment
                    // Check if the membership payment exists
                    // if not create new one
                    $contributions_sql = "SELECT cc.id , mp.contribution_id, mp.id as payment_id FROM civicrm_contribution cc LEFT JOIN civicrm_membership_payment mp ON mp.contribution_id = cc.id WHERE cc.contribution_recur_id = %1";
                    $contributions_params = array(
                      1 =>  array($params['recur_id'],  'Integer')
                    );
                    $contributions_dao = CRM_Core_DAO::executeQuery($contributions_sql, $contributions_params);
                    while($contributions_dao->fetch()) {
                      if (!empty($contributions_dao->contribution_id)) {
                        //Update membership payment
                        $update_membership_payment_sql = "UPDATE civicrm_membership_payment SET membership_id = %1 WHERE id = %2";
                        $update_membership_payment_params = array(
                          1 =>  array($membership_record,   'Integer'),
                          2 =>  array($contributions_dao->payment_id,  'Integer')
                        );
                        CRM_Core_DAO::executeQuery($update_membership_payment_sql, $update_membership_payment_params);
                      } else {
                        //Insert membership payment
                        $insert_membership_payment_sql = "INSERT INTO civicrm_membership_payment SET contribution_id = %2 , membership_id = %1";
                        $insert_membership_payment_params = array(
                          1 =>  array($membership_record,   'Integer'),
                          2 =>  array($contributions_dao->id,  'Integer')
                        );
                        CRM_Core_DAO::executeQuery($insert_membership_payment_sql, $insert_membership_payment_params);
                      }
                    }
                  }
                }
              }
            }
        }
        
        CRM_Core_DAO::executeQuery($sql, $recur_params);
        $recur_id = ($params['action'] == 'add' ? CRM_Core_DAO::singleValueQuery('SELECT LAST_INSERT_ID()') : $params['recur_id']);
        if ($params['action'] == 'add') {
          CRM_Core_DAO::executeQuery("REPLACE INTO civicrm_contribution_recur_offline (recur_id) VALUES (%1)", array(1 => array($recur_id, 'Integer')));
        }

        $session = CRM_Core_Session::singleton();
        CRM_Core_Session::setStatus($status);  
        CRM_Utils_System::redirect(
            CRM_Utils_System::url('civicrm/contact/view', 'reset=1&cid=' . $params['cid'], false, null, false, true)
	    );

      }
}
