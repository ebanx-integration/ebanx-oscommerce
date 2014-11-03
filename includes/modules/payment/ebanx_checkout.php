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

class ebanx_checkout
{
    var $code, $title, $description, $enabled, $payment, $checkoutURL, $status;
    
    function ebanx_checkout()
    {
        global $order;
        $this->signature = 'ebanx|ebanx_checkout|1.0|1.0';
        $this->code = 'ebanx_checkout';
        $this->title = MODULE_PAYMENT_EBANX_CHECKOUT_TEXT_TITLE;
        $this->description = MODULE_PAYMENT_EBANX_CHECKOUT_TEXT_DESCRIPTION;
        $this->sort_order = MODULE_PAYMENT_EBANX_CHECKOUT_SORT_ORDER;
        $this->enabled = ((MODULE_PAYMENT_EBANX_CHECKOUT_STATUS == 'True') ? true : false);

        if (is_object($order))
        {
            $this->update_status();
        }
    }

    function update_status()
    {
        global $order;

        if (($this->enabled == true) && ((int)MODULE_PAYMENT_EBANX_CHECKOUT_ZONE > 0))
        {
            $check_flag = false;
            $check_query = tep_db_query("select zone_id from " . TABLE_ZONES_TO_GEO_ZONES .
            " where geo_zone_id = '" . MODULE_PAYMENT_EBANX_CHECKOUT_ZONE . "' and zone_country_id = '" .
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
        if($order->billing['country']['title'] == 'Brazil' || $order->billing['country']['title'] == 'Peru'){
              
            $fieldsArray = array();

            $selection   = array('id' => $this->code,
                                 'module' => MODULE_PAYMENT_EBANX_CHECKOUT_TEXT_CATALOG_TITLE
                                );
        }
        return $selection;
    }
  
    function pre_confirmation_check()
    {
        return false;
    }

    function confirmation()
    {
        return false;
    }

    function process_button()
    {
        return false;
    }

    function before_process()
    {
        global $_POST, $order, $sendto, $currency, $charge, $cart;

        // Street number workaround
        $streetNumber = preg_replace('/[\D]/', '', $order->billing['street_address']);
        $streetNumber = ($streetNumber > 0) ? $streetNumber : '1';

        // Creates notification and return URL
        $returnURL = tep_href_link('ebanx_return.php', '', 'SSL', false, false, true);
        $callbackURL = tep_href_link('ebanx_notification.php', '', 'SSL', false, false, true);
      
        // Sets EBANX Configuration parameters within lib
        \Ebanx\Config::set(array(
             'integrationKey' => MODULE_PAYMENT_EBANX_CHECKOUT_INTEGRATIONKEY
            ,'testMode'       => MODULE_PAYMENT_EBANX_CHECKOUT_TESTMODE == 'True'
                          )
        );

        //Country title workaround
        if($order->billing['country']['title'] == 'Brazil')
        {
            $country = 'BR';
        }
 
        if($order->billing['country']['title'] == 'Peru')
        {
            $country = 'PE';
        }

        // Creates next order ID
        $last_order_id = tep_db_query("select * from " . TABLE_ORDERS . " order by orders_id desc limit 1");
        $new_order_id = tep_db_fetch_array($last_order_id);
        $new_order_id = (1 + $new_order_id['orders_id']);
      
        // Creates array and submits data to EBANX
        $submit = \Ebanx\Ebanx::doRequest(array(
            'currency_code'     =>  $order->info['currency']
            , 'amount'            =>  $order->info['total']
            , 'name'              =>  $order->billing['firstname'] . ' ' . $order->billing['lastname']
            , 'email'             =>  $order->customer['email_address']
            , 'payment_type_code' =>  '_all'
            , 'merchant_payment_code' => $new_order_id
            , 'country'           => $country
            , 'zipcode'           => $order->billing['postcode']
            , 'phone_number'      => $order->customer['telephone']
            )
        ); 

      $this->status = $submit->status;
      $this->message = $submit->status_message;
      if($this->status == 'SUCCESS')
      {   
          // Resets cart, saves Checkout URL and stores data in database
          $cart->reset(true);
          $this->checkoutURL = $submit->redirect_url;
          $hash = $submit->payment->hash;
          tep_db_query("insert into ebanx_data (order_id, customers_cpf, hash) values ('" . $new_order_id . "', '" . $_POST['customerb_cpf'] . "', '" . $hash . "')");
      }

      return false;
    }

    function after_process()
    {
        // Redirects to Checkout URL
        if($this->status == 'SUCCESS')
        {
            tep_redirect($this->checkoutURL);
        }

        else
        {
            tep_redirect(tep_href_link(FILENAME_CHECKOUT_PAYMENT, 'error_message=' . stripslashes($this->message), 'SSL'));
        }

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
            $check_query = tep_db_query("select configuration_value from " . TABLE_CONFIGURATION . " where configuration_key = 'MODULE_PAYMENT_EBANX_CHECKOUT_STATUS'");
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
        
        // Creates status "Cancelled" for EBANX orders
        $order_status = 'Cancelled';
        $status_id = 0;
        $check_query = tep_db_query("select orders_status_id from ".TABLE_ORDERS_STATUS." where orders_status_name = '".$order_status."' limit 1");
        if (tep_db_num_rows($check_query) < 1)
        {
            $status_query = tep_db_query("select max(orders_status_id) as status_id from ".TABLE_ORDERS_STATUS);
            $status = tep_db_fetch_array($status_query);
            $status_id = $status['status_id']+1;
            $languages = tep_get_languages();
            $flags_query = tep_db_query("describe " . TABLE_ORDERS_STATUS . " public_flag");
            if (tep_db_num_rows($flags_query) == 1)
            {
              foreach ($languages as $lang)
              {
                tep_db_query("insert into ".TABLE_ORDERS_STATUS." (orders_status_id, language_id, orders_status_name, public_flag) values ('".$status_id."', '".$lang['id']."', "."'".$order_status."', 1)");
              }
            }
            else
            {
              foreach ($languages as $lang)
              {
                tep_db_query("insert into ".TABLE_ORDERS_STATUS." (orders_status_id, language_id, orders_status_name) values ('".$status_id."', '".$lang['id']."', "."'".$order_status."')");
              } 
            }
        }
        else
        {
            $check = tep_db_fetch_array($check_query);
            $status_id = $check['orders_status_id'];
        }

        // // Sets Integration Key if already existing in TABLE_CONFIGURATION
        $check_query = tep_db_query("select configuration_value from " . TABLE_CONFIGURATION . " c where c.configuration_key = 'MODULE_PAYMENT_EBANX_INTEGRATIONKEY'");
        $intKey = tep_db_fetch_array($check_query);

        if(isset($check_query))
        {
            $integrationKey = $intKey['configuration_value'];
        }

        tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Enable Ebanx Checkout', 'MODULE_PAYMENT_EBANX_CHECKOUT_STATUS', 'True', 'Do you want to accept EBANX Boleto and TEF payments?', '6', '1', 'tep_cfg_select_option(array(\'True\', \'False\'), ', now())");
        tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Integration Key', 'MODULE_PAYMENT_EBANX_CHECKOUT_INTEGRATIONKEY', '". $integrationKey . "', 'Your EBANX unique integration key', '6', '0', now())");
        tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Test Mode', 'MODULE_PAYMENT_EBANX_CHECKOUT_TESTMODE', 'True', 'Test Mode?', '6', '0', 'tep_cfg_select_option(array(\'True\', \'False\'), ', now())");
        tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, use_function, set_function, date_added) values ('Payment Zone', 'MODULE_PAYMENT_EBANX_CHECKOUT_ZONE', '0', 'If a zone is selected, only enable this payment method for that zone.', '6', '2', 'tep_get_zone_class_title', 'tep_cfg_pull_down_zone_classes(', now())");
    }

    function remove()
    {
        tep_db_query("delete from " . TABLE_CONFIGURATION . " where configuration_key in ('" . implode("', '", $this->keys()) . "')");
    }

    function keys()
    {
        return array('MODULE_PAYMENT_EBANX_CHECKOUT_STATUS', 'MODULE_PAYMENT_EBANX_CHECKOUT_INTEGRATIONKEY', 'MODULE_PAYMENT_EBANX_CHECKOUT_TESTMODE', 'MODULE_PAYMENT_EBANX_CHECKOUT_ZONE');
    }
  
  }
