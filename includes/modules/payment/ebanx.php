<?php

/**
 * Copyright (c) 2014, EBANX Tecnologia da Informação Ltda.
 *  All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * Redistributions of source code must retain the above copyright notice, this
 * list of conditions and the following disclaimer.
 *
 * Redistributions in binary form must reproduce the above copyright notice,
 * this list of conditions and the following disclaimer in the documentation
 * and/or other materials provided with the distribution.
 *
 * Neither the name of EBANX nor the names of its
 * contributors may be used to endorse or promote products derived from
 * this software without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
 * AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
 * DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE
 * FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL
 * DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR
 * SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER
 * CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY,
 * OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 */

require_once 'ebanx/ebanx-php-master/src/autoload.php';

class ebanx
{
    var $code, $title, $description, $enabled, $payment;

    function ebanx()
    {
        global $order;
        $this->signature = 'ebanx|ebanx|1.0|1.0';
        $this->code = 'ebanx';
        $this->title = MODULE_PAYMENT_EBANX_TEXT_TITLE;
        $this->description = MODULE_PAYMENT_EBANX_TEXT_DESCRIPTION;
        $this->sort_order = MODULE_PAYMENT_EBANX_SORT_ORDER;
        $this->enabled = ((MODULE_PAYMENT_EBANX_STATUS == 'True') ? true : false);

        if (MODULE_PAYMENT_EBANX_INSTALLMENTS == 'True')
        {
            $this->num_installments = MODULE_PAYMENT_EBANX_MAXINSTALLMENTS;
        }

        if (is_object($order))
        {
            $this->update_status();
        }
    }                  

    function update_status()
    {        
        global $order;

        if (($this->enabled == true) && ((int)MODULE_PAYMENT_EBANX_ZONE > 0)) 
        {
            $check_flag = false;
            $check_query = tep_db_query("select zone_id from " . TABLE_ZONES_TO_GEO_ZONES . 
            " where geo_zone_id = '" . MODULE_PAYMENT_EBANX_ZONE . "' and zone_country_id = '" .
            $order->billing['country']['id'] . "' order by zone_id");
            while ($check = tep_db_fetch_array($check_query))
            {
                if ($check['zone_id'] < 1)
                {
                    $check_flag = true;
                    break;
                } 
                elseif ($check['zone_id'] == $order->billing['zone_id'])
                {
                    $check_flag = true;
                    break;
                }
            }

            if ($check_flag == false)
            {
                $this->enabled = false;
            }
        }
    }

    function javascript_validation()
    {
        return false;
    }

    function selection()
    {
        global $order;

        //Hides for non-brazilian customers
        if($order->billing['country']['title'] != 'Brazil')
        {
            return $selection;
        }

        // Creates dropdown list for expiring months
        for ($i=1; $i<13; $i++)
        {
            $expires_month[] = array('id' => sprintf('%02d', $i), 'text' => strftime('%m',mktime(0,0,0,$i,1,2000)));
        }

        // Creates dropdown list for expiring year
        $today = getdate();
        for ($i=$today['year']; $i < $today['year']+10; $i++)
        {
            $expires_year[] = array('id' => strftime('%y',mktime(0,0,0,1,1,$i)), 'text' => strftime('%Y',mktime(0,0,0,1,1,$i)));
        }

        $onFocus = ' onfocus="methodSelect(\'pmt-' . $this->code . '\')"';

        $fieldsArray   = array();

        // This section creates custom input fields for EBANX
        $fieldsArray[] = array('title' => MODULE_PAYMENT_EBANX_TEXT_CUSTOMER_CPF,
                               'field' => tep_draw_input_field('ebanx_cpf', '',
                               'id="'.$this->code.'-cpf"'. $onFocus),
                               'tag' => $this->code.'-cpf');

        $fieldsArray[] = array('title' => MODULE_PAYMENT_EBANX_TEXT_CREDIT_CARD_OWNER,
                               'field' => tep_draw_input_field('ebanx_cc_owner', $order->billing['firstname'] . ' ' . $order->billing['lastname'],
                               'id="'.$this->code.'-cc-owner"'. $onFocus),
                               'tag' => $this->code.'-cc-owner');

        $fieldsArray[] = array('title' => MODULE_PAYMENT_EBANX_TEXT_CREDIT_CARD_NUMBER,
                               'field' => tep_draw_input_field('ebanx_cc_number', '',
                               'id="'.$this->code.'-cc-number"' . $onFocus),
                               'tag' => $this->code.'-cc-number');

        $fieldsArray[] = array('title' => MODULE_PAYMENT_EBANX_TEXT_CREDIT_CARD_EXPIRES,
                               'field' => tep_draw_pull_down_menu('ebanx_cc_expires_month', $expires_month, '',
                               'id="'.$this->code.'-cc-expires-month"' . $onFocus) . '&nbsp;' . tep_draw_pull_down_menu('ebanx_cc_expires_year', $expires_year, '',
                               'id="'.$this->code.'-cc-expires-year"' . $onFocus),
                               'tag' => $this->code.'-cc-expires-month');
                         
        $fieldsArray[] = array('title' => MODULE_PAYMENT_EBANX_TEXT_CVV,
                               'field' => tep_draw_input_field('ebanx_cc_cvv','', 'size="4", maxlength="4" ' .
                               'id="'.$this->code.'-cc-cvv"' . $onFocus),
                               'tag' => $this->code.'-cc-cvv');
                        
        // This section creates the installments input fields
        if (MODULE_PAYMENT_EBANX_INSTALLMENTS == 'True')
        {
            for ($i=0; $i < $this->num_installments; $i++)
            {
                $installments[$i] = array('id' => $i+1, 'text' => $i+1 );   
            }


            $fieldsArray[] = array('title' => MODULE_PAYMENT_EBANX_TEXT_INSTALLMENTS,
                                   'field' => tep_draw_pull_down_menu('ebanx_installments', $installments, '', 'id="'.$this->code.'-ebanx-cc-installments"' .  $onFocus . ' autocomplete="on"'),
                                   'tag' => $this->code.'-ebanx-cc-installments');
        } 

        $selection = array('id' => $this->code,
                           'module' => MODULE_PAYMENT_EBANX_TEXT_CATALOG_TITLE,
                           'fields' => $fieldsArray);

        return $selection;
    }

    function pre_confirmation_check()
    {
        // This function validates all credit card submitted values
        global $db, $_POST, $messageStack, $_SESSION;
        include (DIR_WS_CLASSES . 'cc_validation.php');

        $cc_validation = new cc_validation();
        $result = $cc_validation->validate($_POST['ebanx_cc_number'], $_POST['ebanx_cc_expires_month'], $_POST['ebanx_cc_expires_year'], $_POST['ebanx_cc_cvv']);
        $error = '';
        switch ($result) 
        {
            case -1:
                $error = sprintf(TEXT_CCVAL_ERROR_UNKNOWN_CARD, substr($cc_validation->cc_number, 0, 4));
                break;
            case -2:
            case -3:
            case -4:
                $error = TEXT_CCVAL_ERROR_INVALID_DATE;
                break;
            case false:
                $error = TEXT_CCVAL_ERROR_INVALID_NUMBER;
                break;
        }

        if (($result == false) || ($result < 1)) 
        {
            tep_redirect(tep_href_link(FILENAME_CHECKOUT_PAYMENT, 'error_message=' . stripslashes($error), 'SSL'));
        }
        
        // Validates submitted CPF
        if (!$this->validaCPF($_POST['ebanx_cpf']))
        {
            tep_redirect(tep_href_link(FILENAME_CHECKOUT_PAYMENT, 'error_message=' . stripslashes(MODULE_PAYMENT_EBANX_TEXT_CPF_ERROR), 'SSL'));
        }
           
        $this->cc_card_type = strtolower($cc_validation->cc_type);
        $this->cc_card_number = $cc_validation->cc_number;
        $this->cc_expiry_month = $cc_validation->cc_expiry_month;
        $this->cc_expiry_year = $cc_validation->cc_expiry_year;

        return false;
    }

    function confirmation()
    {
        global $order;

        $fieldsArray = array();

        $fieldsArray[] = array('title' => MODULE_PAYMENT_EBANX_TEXT_CREDIT_CARD_TYPE,
                               'field' => $this->cc_card_type
                               );

        $fieldsArray[] = array('title' => MODULE_PAYMENT_EBANX_TEXT_CREDIT_CARD_OWNER,
                               'field' => $_POST['ebanx_cc_owner']
                               );

        $fieldsArray[] = array('title' => MODULE_PAYMENT_EBANX_TEXT_CREDIT_CARD_NUMBER,
                               'field' => substr($this->cc_card_number, 0, 0) . str_repeat('X', (strlen($this->cc_card_number) - 4)) . substr($this->cc_card_number, -4));
                 
        if (isset($_POST['ebanx_installments'])) {
            $fieldsArray[] = array('title' => MODULE_PAYMENT_EBANX_TEXT_INSTALLMENTS,
                                   'field' => $_POST['ebanx_installments']
                                   );
        }

        $confirmation = array('fields' => $fieldsArray);

        return $confirmation;
    }
      
    function process_button()
    {
        global $db;
  
        $process_button_string = tep_draw_hidden_field('cc_owner', $_POST['ebanx_cc_owner']) .
        tep_draw_hidden_field('cc_expires', $this->cc_expiry_month . '/' . $this->cc_expiry_year) .
        tep_draw_hidden_field('cc_number', $this->cc_card_number);
  
        $process_button_string .= tep_draw_hidden_field('cc_cvv', $_POST['ebanx_cc_cvv']);
        $process_button_string .= tep_draw_hidden_field('cc_type', $this->cc_card_type);
        $process_button_string .= tep_draw_hidden_field('customer_cpf', $_POST['ebanx_cpf']);
        $process_button_string .= tep_draw_hidden_field('instalments', $_POST['ebanx_installments']);

        return $process_button_string;
    }

    function before_process()
    {
        global $_POST,  $order, $sendto, $currency, $charge,$db, $messageStack;



        //State code workaround
        if(strlen($order->billing['state']) > '2')
        {
            $state = tep_db_query("select * from " . TABLE_ZONES . " where zone_name = " . $order->billing['state']);
            $state = tep_db_fetch_array($state);
            //tep_redirect(tep_href_link(FILENAME_CHECKOUT_PAYMENT, 'error_message=' . MODULE_PAYMENT_EBANX_TEXT_STATE_ERROR , 'SSL'));

        }
        var_dump($state);
            die;

        // Street number workaround
        $streetNumber = preg_replace('/[\D]/', '', $order->billing['street_address']);
        $streetNumber = ($streetNumber > 0) ? $streetNumber : '1';

        // Sets EBANX Configuration parameters within lib
        \Ebanx\Config::set(array(
            'integrationKey' => MODULE_PAYMENT_EBANX_INTEGRATIONKEY
           ,'testMode'       => MODULE_PAYMENT_EBANX_TESTMODE == 'True'
        ));
        \Ebanx\Config::setDirectMode(true);

        // Creates notification URL
        $callbackURL = tep_href_link('ebanx_notification.php', '', 'SSL', false, false, true);

        //Country title workaround
        if($order->billing['country']['title'] == 'Brazil')
        {
          $country = 'BR';
        }

        // Creates next order ID
        $last_order_id = tep_db_query("select * from " . TABLE_ORDERS . " order by orders_id desc limit 1");
        $new_order_id = tep_db_fetch_array($last_order_id);
        $new_order_id = (1 + $new_order_id['orders_id']);

        // If has installments, adjust total
        if (isset($_POST['instalments']) &&  $_POST['instalments'] > '1')
        {
            $interestRate = floatval(MODULE_PAYMENT_EBANX_INSTALLMENTSRATE);
            $value = number_format((($order->info['total'] * (100 + $interestRate)) / 100.0) , 2);
        }
        else
        {
            $_POST['instalments'] = '1';
            $value = ($order->info['total']);
        }

        // Retrieves customer's date of birth
        $dob_info = tep_db_query("SELECT customers_dob FROM " . TABLE_CUSTOMERS . " WHERE customers_id = " . $_SESSION['customer_id'] . " LIMIT 1");
        $fetch = tep_db_fetch_array($dob_info);
        $date_time = explode(" ", $fetch['customers_dob']);
        if ($date_time[0] != '0000-00-00')
        {   
            $dates = explode("-", $date_time[0]);
            $dob_info = $dates[2] . '/' . $dates[1] . '/' . $dates[0];
        }
        else
        {
            $dob_info = '12/01/1987';
        }

        // Creates array for sending EBANX
        $submit = array(
           'integration_key' => MODULE_PAYMENT_EBANX_INTEGRATIONKEY
           ,'operation'       => 'request'
           ,'mode'            => 'full'
           ,'payment'         => array(
                'merchant_payment_code' => $new_order_id
                ,'currency_code'         => $order->info['currency']
                ,'name'  => $order->customer['firstname'].' '.$order->customer['lastname']
                ,'email' => $order->customer['email_address']
                ,'birth_date' => $dob_info
                ,'document'   => $_POST['customer_cpf']
                ,'city'       => $order->billing['city']
                ,'state'      => $order->billing['state']
                ,'zipcode'    => $order->billing['postcode']
                ,'street_number' => $streetNumber
                ,'country'    => $country
                ,'phone_number' => $order->customer['telephone']
                ,'address'      => $order->billing['street_address']
                ,'amount_total'       => $value
                ,'instalments'  => $_POST['instalments']
                ,'payment_type_code' => $_POST['cc_type']
                ,'creditcard'   => array(
                    'card_number'  => $_POST['cc_number']
                    ,'card_name'    => $_POST['cc_owner']
                    ,'card_due_date' => $_POST['cc_expires']
                    ,'card_cvv'      => $_POST['cc_cvv']
                )
            )
        );

        //Finally submits the order
        $response = \Ebanx\Ebanx::doRequest($submit);
                                     
        if ($response->status == 'SUCCESS')
        {
            $cpf = $_POST['customer_cpf'];
            $hash = $response->payment->hash;
            tep_db_query("insert into ebanx_data (order_id, customers_cpf, hash) values ('" . $_POST['order_id'] . "', '" . $cpf . "', '" . $hash . "')");
        }
        else
        {
            tep_redirect(tep_href_link(FILENAME_CHECKOUT_PAYMENT, 'error_message=' . stripslashes($response->status_message), 'SSL'));
        }
    }

    function after_process()
    {
        return false;
    }

    function get_error()
    {
        return false;
    }

    function check()
    {
        if (!isset($this->_check))
    {
        $check_query = tep_db_query("select configuration_value from " . TABLE_CONFIGURATION . " where configuration_key = 'MODULE_PAYMENT_EBANX_STATUS'");
        $this->_check = tep_db_num_rows($check_query);
    }
        return $this->_check;
    }

    function install()
    {
        $integrationKey = '0';
        
        //Creates EBANX custom table
        tep_db_query("CREATE TABLE IF NOT EXISTS `". "ebanx_data` (
            `ebanx_id` INT( 11 ) NOT NULL  auto_increment,
            `order_id` VARCHAR( 64 ) NOT NULL ,
            `customers_cpf` VARCHAR( 64 ) NOT NULL ,
            `hash` VARCHAR( 64 ) NOT NULL ,
             PRIMARY KEY  (`ebanx_id`)
             )   AUTO_INCREMENT=1 ;"
        );
        
        // Creates statuses and brazilian states for EBANX orders
        require_once 'ebanx/installer.php';
        $installer = new Installer();
        $installer->install();

        //Sets Integration Key if already existing in TABLE_CONFIGURATION
        $check_query = tep_db_query("select configuration_value from " . TABLE_CONFIGURATION . " c where c.configuration_key = 'MODULE_PAYMENT_EBANX_CHECKOUT_INTEGRATIONKEY'");
        $intKey = tep_db_fetch_array($check_query);
        if(isset($check_query))
        {
            $integrationKey = $intKey['configuration_value'];
        }

        tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Enable Ebanx', 'MODULE_PAYMENT_EBANX_STATUS', 'True', 'Do you want to accept EBANX payments?', '6', '1', 'tep_cfg_select_option(array(\'True\', \'False\'), ', now())");
        tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Integration Key', 'MODULE_PAYMENT_EBANX_INTEGRATIONKEY', '". $integrationKey . "', 'Your EBANX unique integration key', '6', '0', now())");
        tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Test Mode', 'MODULE_PAYMENT_EBANX_TESTMODE', 'True', 'Test Mode?', '6', '0', 'tep_cfg_select_option(array(\'True\', \'False\'), ', now())");
        tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Installments'   ,   'MODULE_PAYMENT_EBANX_INSTALLMENTS', 'False', 'Enable Installments?', '6', '0', 'tep_cfg_select_option(array(\'True\', \'False\'), ', now())");
        tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Maximum Installments Enabled', 'MODULE_PAYMENT_EBANX_MAXINSTALLMENTS', '6', 'Maximum Installments Number', '6', '0',  now())");
        tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Installments rate (%)', 'MODULE_PAYMENT_EBANX_INSTALLMENTSRATE', '10',  'Installments Rate', '6',  '0', now())");
        tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Sort order of display.', 'MODULE_PAYMENT_EBANX_SORT_ORDER', '0', 'Sort order of display. Lowest is displayed first.', '6', '0', now())");
        tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, use_function, set_function, date_added) values ('Payment Zone', 'MODULE_PAYMENT_EBANX_ZONE', '0', 'If a zone is selected, only enable this payment method for that zone.', '6', '2', 'tep_get_zone_class_title', 'tep_cfg_pull_down_zone_classes(', now())");
    }

    function remove()
    {
        tep_db_query("delete from " . TABLE_CONFIGURATION . " where configuration_key in ('" . implode("', '", $this->keys()) . "')");
    }

    function keys()
    {
        return array('MODULE_PAYMENT_EBANX_STATUS', 'MODULE_PAYMENT_EBANX_INTEGRATIONKEY', 'MODULE_PAYMENT_EBANX_TESTMODE', 'MODULE_PAYMENT_EBANX_INSTALLMENTS', 'MODULE_PAYMENT_EBANX_MAXINSTALLMENTS', 'MODULE_PAYMENT_EBANX_INSTALLMENTSRATE', 'MODULE_PAYMENT_EBANX_SORT_ORDER', 'MODULE_PAYMENT_EBANX_ZONE');
    }

    function validaCPF($cpf)
    {   
        $cpf = str_pad(preg_replace('[^0-9]', '', $cpf), 11, '0', STR_PAD_LEFT);
                
        if (strlen($cpf) != 11 || $cpf == '00000000000' || $cpf == '11111111111' || $cpf == '22222222222' || $cpf == '33333333333' || $cpf == '44444444444' || $cpf == '55555555555' || $cpf == '66666666666' || $cpf == '77777777777' || $cpf == '88888888888' || $cpf == '99999999999')
        {
            return false;
        }
        else
        {  
            for ($t = 9; $t < 11; $t++)
            {
                for ($d = 0, $c = 0; $c < $t; $c++)
                {
                    $d += $cpf{$c} * (($t + 1) - $c);
                }
     
                $d = ((10 * $d) % 11) % 10;
     
                if ($cpf{$c} != $d)
                {
                    return false;
                }
            }
            return true;
        }
    }
}