<?php
/*
  $Id: fedex_freight.php,v 0.9 2007/08/08 Brian Burton brian@dynamoeffects.com Exp $

  This module is for use with FedEx's freight shipping service, not with their regular shipping service.
  
  Copyright (c) 2007 Brian Burton - brian@dynamoeffects.com

  Released under the GNU General Public License
*/

  class fxfreight {
    var $code, $title, $description, $icon, $enabled;

// class constructor
    function fxfreight() {
      global $order;

      $this->code = 'fxfreight';
      $this->title = MODULE_SHIPPING_FXFREIGHT_TEXT_TITLE;
      $this->description = MODULE_SHIPPING_FXFREIGHT_TEXT_DESCRIPTION;
      $this->sort_order = MODULE_SHIPPING_FXFREIGHT_SORT_ORDER;
      $this->icon = '';
      $this->tax_class = MODULE_SHIPPING_FXFREIGHT_TAX_CLASS;
     
      //FedEx Freight is only available in the US and Canada, so don't enable it if the customer ain't from these here parts
      $dest_country = $order->delivery['country']['iso_code_2'];
      if (($dest_country == 'US' || $dest_country == 'CA') && MODULE_SHIPPING_FXFREIGHT_STATUS == 'True') {
        $this->enabled = true;
      } else {
        $this->enabled = false;
      }

      if ( ($this->enabled == true) && ((int)MODULE_SHIPPING_FXFREIGHT_ZONE > 0) ) {
        $check_flag = false;
        $check_query = tep_db_query("select zone_id from " . TABLE_ZONES_TO_GEO_ZONES . " where geo_zone_id = '" . MODULE_SHIPPING_FXFREIGHT_ZONE . "' and zone_country_id = '" . $order->delivery['country']['id'] . "' order by zone_id");
        while ($check = tep_db_fetch_array($check_query)) {
          if ($check['zone_id'] < 1) {
            $check_flag = true;
            break;
          } elseif ($check['zone_id'] == $order->delivery['zone_id']) {
            $check_flag = true;
            break;
          }
        }

        if ($check_flag == false) {
          $this->enabled = false;
        }
      }
    }

// class methods
    function quote($method = '') {
      global $order, $cart;
      
      $error_msg = '';
      
      //First, we get the customer's zipcode and country in the right format.
      $dest_country = $order->delivery['country']['iso_code_2'];
      
      if ($dest_country == 'US') {
        $dest_zip = preg_replace('/[^0-9]/i', '', strtoupper($order->delivery['postcode']));
        $dest_zip = substr($dest_zip, 0, 5);
      } elseif ($dest_country == 'CA') {
        $dest_zip = preg_replace('/[^0-9A-Z]/i', '', strtoupper($order->delivery['postcode']));
        $dest_zip = substr($dest_zip, 0, 6);
      } else {
        $error_msg = '<br>' . MODULE_SHIPPING_FXFREIGHT_TEXT_ERROR_BAD_COUNTRY;
      }

      //Format the shipping zip code as well
      if (MODULE_SHIPPING_FXFREIGHT_SHIP_COUNTRY == 'US') {
        $ship_zip = preg_replace('/[^0-9]/i', '', MODULE_SHIPPING_FXFREIGHT_SHIP_ZIP);
        $ship_zip = substr($ship_zip, 0, 5);
      } elseif (MODULE_SHIPPING_FXFREIGHT_SHIP_COUNTRY == 'CA') {
        $ship_zip = preg_replace('/[^0-9A-Z]/i', '', strtoupper(MODULE_SHIPPING_FXFREIGHT_SHIP_ZIP));
        $ship_zip = substr($ship_zip, 0, 6);
      }
	  
      if ($error_msg == '') {
        /* Now, build an array of URLs to call.  Their server only allows 6 items 
         * at a time, so this section will build multiple calls if necessary to
         * get a full quote.
         */
        
        //The base URL
        $base_URL = 'http://www.fedexfreight.fedex.com/XMLRating.jsp?';
        $base_URL .= 'as_shipterms=' . MODULE_SHIPPING_FXFREIGHT_SHIP_TERMS;
        $base_URL .= '&as_shzip=' . $ship_zip;
        $base_URL .= '&as_shcntry=' . MODULE_SHIPPING_FXFREIGHT_SHIP_COUNTRY;
        $base_URL .= '&as_cnzip=' . $dest_zip;
        $base_URL .= '&as_cncntry=' . $dest_country;
        $base_URL .= '&as_iamthe=shipper';
        if (trim(MODULE_SHIPPING_FXFREIGHT_ACCT_NUM) != '') {
          $base_URL .= '&as_acctnbr=' . MODULE_SHIPPING_FXFREIGHT_ACCT_NUM;
        }
        
        //Get the shopping cart contents
        $products = $cart->get_products();
        $url_attr = '';
        $x = 1;
        $fxf_urls = array();
        $n = sizeof($products);
        
        for ($i=0; $i<$n; $i++) {
          $prod_query = tep_db_query("SELECT products_fxf_class, products_fxf_desc, products_fxf_nmfc, products_fxf_haz, products_fxf_freezable FROM " . TABLE_PRODUCTS . " WHERE products_id = '".$products[$i]['id']."'");
          $prod_info = tep_db_fetch_array($prod_query);
          //class, weight, pcs, descr, nmfc, haz, freezable
          $url_attr .= '&as_class' . $x . '=' . $prod_info['products_fxf_class'];
          $url_attr .= '&as_weight' . $x . '=' . ($products[$i]['quantity'] * $products[$i]['weight'] < 1 ? 1 : ceil($products[$i]['quantity'] * $products[$i]['weight']));
          $url_attr .= '&as_pcs' . $x . '=' . $products[$i]['quantity'];

          if (trim($prod_info['products_fxf_desc']) != '') {
            $url_attr .= '&as_descr' . $x . '=' . urlencode($prod_info['products_fxf_desc']);
          }
          if (trim($prod_info['products_fxf_nmfc']) != '') {
            $url_attr .= '&as_nmfc' . $x . '=' . urlencode($prod_info['products_fxf_nmfc']);
          }
          if (trim($prod_info['products_fxf_haz']) != '') {
            $url_attr .= '&as_haz' . $x . '=' . $prod_info['products_fxf_haz'];
          }
          if (trim($prod_info['products_fxf_freezable']) != '') {
            $url_attr .= '&as_freezable' . $x . '=' . $prod_info['products_fxf_freezable'];
          }          

          //Six is the maximum number of products that FedEx will take at a time.
          if ($x >= 6) {
            $fxf_urls[] = array('pcs' => '6', 'url' => $base_URL . $url_attr);
            $x = 1;
            $url_attr = '';
          } else {
            $x++;
          }
        }
        
        if ($url_attr != '') $fxf_urls[] = array('pcs' => $x - 1, 'url' => $base_URL . $url_attr);
        
        $total_shipping_price = 0;
        
        //URL array is finished, now start calling FedEx.
        $n = sizeof($fxf_urls);
        for ($i=0; $i<$n; $i++) {
          $ship_price = $this->getFXFQuote($fxf_urls[$i]['url']);
          
          if (!$ship_price) { 
            //Currently, shipping within CA is not supported
            if (MODULE_SHIPPING_FXFREIGHT_SHIP_COUNTRY == 'CA' && $dest_country == 'CA') {
              $error_msg .= '<br>' . MODULE_SHIPPING_FXFREIGHT_TEXT_ERROR_SHIPPING_WITHIN_CA . '<br>';
            } else {
              $error_msg .= '<br>' . MODULE_SHIPPING_FXFREIGHT_TEXT_ERROR_BAD_RESPONSE . '<br>' . $fxf_urls[$i]['url'];
            }
            break;
          }
          $total_shipping_price += $ship_price + ((float)MODULE_SHIPPING_FXFREIGHT_HANDLING * $fxf_urls[$i]['pcs']);
        }
      }
      
      if (!$error_msg) {
        $this->quotes = array('id' => $this->code,
                              'module' => MODULE_SHIPPING_FXFREIGHT_TEXT_TITLE,
                              'methods' => array(array('id' => $this->code,
                                                       'title' => MODULE_SHIPPING_FXFREIGHT_TEXT_WAY,
                                                       'cost' => $total_shipping_price)));            
  
        if ($this->tax_class > 0) {
          $this->quotes['tax'] = tep_get_tax_rate($this->tax_class, $order->delivery['country']['id'], $order->delivery['zone_id']);
        }
  
        if (tep_not_null($this->icon)) $this->quotes['icon'] = tep_image($this->icon, $this->title);
      } else {
      
        switch (MODULE_SHIPPING_FXFREIGHT_ERROR_ACTION) {
          case 'Email':
            if (tep_session_is_registered('customer_first_name') && tep_session_is_registered('customer_id')) {
              $error_msg_heading = 'This error log was generated when customer ' . $_SESSION['customer_first_name'] . ' (Customer ID: ' . $_SESSION['customer_id'] . ') checked out on ' . date('Y-m-d H:i') . ": \r\n\r\n";
            }
            tep_mail(STORE_OWNER, STORE_OWNER_EMAIL_ADDRESS, 'FedEx Freight Error Log ' . date('Y-m-d H:i'), $error_msg_heading . $error_msg , STORE_OWNER, STORE_OWNER_EMAIL_ADDRESS);

          case 'None':
            $error_msg = '';
            break;
        }
        
        $this->quotes = array('module' => $this->title,
                              'error' => MODULE_SHIPPING_FXFREIGHT_TEXT_ERROR_DESCRIPTION . $error_msg);
      }
      return $this->quotes;
    }
    
    function getFXFQuote($url) {
      $isError = false;
      $connectMethod = false;
      
      if (($fp = @fopen($url, "r"))) {
        $connectMethod = 'fopen';
        
        $data = fread($fp, 4096);
        
      } elseif (function_exists('curl_init')) {
        $connectMethod = 'curl';
        
        $ch = curl_init(); 
        
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 180);
        curl_setopt($ch, CURLOPT_URL, $url); 
        
        $data = curl_exec($ch);
      }
      
      

      if ($connectMethod) {
        if (strpos($fp, 'RATINGERROR') === true || strpos($data, '<NetFreightCharges>') === false) {
          return false;
        } else {
          
          $start_pos = strpos($data, '<NetFreightCharges>') + 20;
          $string_len = strpos($data, '</NetFreightCharges>') - $start_pos;
          $shipping_price = str_replace(',', '', substr($data, $start_pos, $string_len));
        
          if (is_numeric($shipping_price)) {
            return $shipping_price;
          } else {
            return false;
          }
        }
      } else {
        return false;
      }
    }
    
    function check() {
      if (!isset($this->_check)) {
        $check_query = tep_db_query("select configuration_value from " . TABLE_CONFIGURATION . " where configuration_key = 'MODULE_SHIPPING_FXFREIGHT_STATUS'");
        $this->_check = tep_db_num_rows($check_query);
      }
      return $this->_check;
    }

    function install() {
      tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Enable FedEx Freight Shipping', 'MODULE_SHIPPING_FXFREIGHT_STATUS', 'True', 'Do you want to offer FedEx Freight shipping?', '6', '0', 'tep_cfg_select_option(array(\'True\', \'False\'), ', now())");
      tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Shipping Terms', 'MODULE_SHIPPING_FXFREIGHT_SHIP_TERMS', 'prepaid', 'Will these shipments be prepaid or COD? (This is here for future dev.  No COD support right now)', '6', '0', 'tep_cfg_select_option(array(\'prepaid\'), ', now())");
      tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Shipper\'s Zip Code', 'MODULE_SHIPPING_FXFREIGHT_SHIP_ZIP', '', 'Enter the zip code of where these shipments will be sent from. (Required)', '6', '1', now())");
      tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Shipper\'s Country', 'MODULE_SHIPPING_FXFREIGHT_SHIP_COUNTRY', 'US', 'Select the country where these shipments will be sent from.', '6', '0', 'tep_cfg_select_option(array(\'US\', \'CA\'), ', now())");
      tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Account Number', 'MODULE_SHIPPING_FXFREIGHT_ACCT_NUM', '', 'If you have a FedEx Freight account number, enter it here. (Optional)', '6', '1', now())");
      tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Declare Shipment Value?', 'MODULE_SHIPPING_FXFREIGHT_DECLARE_VALUE', 'False', 'Do you want to declare the value of the shipments? (the order total will be used)', '6', '0', 'tep_cfg_select_option(array(\'True\', \'False\'), ', now())");
      tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Residential Pickup', 'MODULE_SHIPPING_FXFREIGHT_RES_PICKUP', 'False', 'Will FedEx be picking up the shipments at a residence?', '6', '0', 'tep_cfg_select_option(array(\'True\', \'False\'), ', now())");
      tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Lift Gate', 'MODULE_SHIPPING_FXFREIGHT_LIFT_GATE', 'False', 'Will FedEx need a lift gate to pick up the shipments?', '6', '0', 'tep_cfg_select_option(array(\'True\', \'False\'), ', now())");
      tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Error Logs', 'MODULE_SHIPPING_FXFREIGHT_ERROR_ACTION', 'Email', 'If FedEx kicks back an error, how do you want to display it? (Email to store owner, display to customer, or none)', '6', '0', 'tep_cfg_select_option(array(\'Email\', \'Display\', \'None\'), ', now())");
      tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Handling Fee', 'MODULE_SHIPPING_FXFREIGHT_HANDLING', '0', 'Handling fee for this shipping method (per item).', '6', '0', now())");
      tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, use_function, set_function, date_added) values ('Tax Class', 'MODULE_SHIPPING_FXFREIGHT_TAX_CLASS', '0', 'Use the following tax class on the shipping fee.', '6', '0', 'tep_get_tax_class_title', 'tep_cfg_pull_down_tax_classes(', now())");
      tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, use_function, set_function, date_added) values ('Shipping Zone', 'MODULE_SHIPPING_FXFREIGHT_ZONE', '0', 'If a zone is selected, only enable this shipping method for that zone.', '6', '0', 'tep_get_zone_class_title', 'tep_cfg_pull_down_zone_classes(', now())");
      tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Sort order of display.', 'MODULE_SHIPPING_FXFREIGHT_SORT_ORDER', '0', 'Sort order of display. Lowest is displayed first.', '6', '0', now())");
      
      $col_query = tep_db_query("SHOW COLUMNS FROM " . TABLE_PRODUCTS);
      $sql = array(
        'products_fxf_class' => "`products_fxf_class` VARCHAR( 3 ) DEFAULT '050' NOT NULL"
        'products_fxf_desc' => "`products_fxf_desc` VARCHAR( 100 )"
        'products_fxf_nmfc' => "`products_fxf_nmfc` VARCHAR( 100 )"
        'products_fxf_haz' => "`products_fxf_haz` TINYINT DEFAULT '0' NOT NULL"
        'products_fxf_freezable' => "`products_fxf_freezable` TINYINT DEFAULT '0' NOT NULL"
      );
      
      while ($col = tep_db_fetch_array($col_query)) {
        switch ($col['Field']) {
          case 'products_fxf_class':
          case 'products_fxf_desc':
          case 'products_fxf_nmfc':
          case 'products_fxf_haz':
          case 'products_fxf_freezable':
            unset($sql[$col['Field']]);
            break;
        }
      }
      
      if (count($sql > 0)) {
        $sql = implode(',', $sql);
        tep_db_query("ALTER TABLE `" . TABLE_PRODUCTS . "` ADD " . $sql);
      }
    }

    function remove() {
      tep_db_query("delete from " . TABLE_CONFIGURATION . " where configuration_key in ('" . implode("', '", $this->keys()) . "')");
    }

    function keys() {
      return array('MODULE_SHIPPING_FXFREIGHT_STATUS', 'MODULE_SHIPPING_FXFREIGHT_SHIP_TERMS', 'MODULE_SHIPPING_FXFREIGHT_SHIP_ZIP', 'MODULE_SHIPPING_FXFREIGHT_SHIP_COUNTRY', 'MODULE_SHIPPING_FXFREIGHT_ACCT_NUM', 'MODULE_SHIPPING_FXFREIGHT_DECLARE_VALUE', 'MODULE_SHIPPING_FXFREIGHT_RES_PICKUP', 'MODULE_SHIPPING_FXFREIGHT_LIFT_GATE', 'MODULE_SHIPPING_FXFREIGHT_ERROR_ACTION', 'MODULE_SHIPPING_FXFREIGHT_HANDLING', 'MODULE_SHIPPING_FXFREIGHT_TAX_CLASS', 'MODULE_SHIPPING_FXFREIGHT_ZONE', 'MODULE_SHIPPING_FXFREIGHT_SORT_ORDER');
    }
  }
?>