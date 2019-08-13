<?php

/**
 * Class of Magento core helper abstraction
 * Class Cartsguru_Helper_Data
 */
 class Cartsguru_Helper_Data extends Mage_Core_Helper_Abstract
 {
     // Get customer language from browser
     public function getBrowserLanguage()
     {
         if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
             foreach (explode(",", strtolower($_SERVER['HTTP_ACCEPT_LANGUAGE'])) as $accept) {
                 if (preg_match("!([a-z-]+)(;q=([0-9\\.]+))?!", trim($accept), $found)) {
                     $langs[] = $found[1];
                     $quality[] = (isset($found[3]) ? (float) $found[3] : 1.0);
                 }
             }
             // Order the codes by quality
             array_multisort($quality, SORT_NUMERIC, SORT_DESC, $langs);
             // get list of stores and use the store code for the key
             $stores = Mage::app()->getStores(false, true);
             // iterate through languages found in the accept-language header
             foreach ($langs as $lang) {
                 $lang = substr($lang, 0, 2);
                 return $lang;
             }
         }
         return null;
     }

     // Get customer group name
     public function getCustomerGroupName()
     {
         $groupId = Mage::getSingleton('customer/session')->getCustomerGroupId();
         $groupName = Mage::getSingleton('customer/group')->load($groupId)->getData('customer_group_code');
         return strtolower($groupName);
     }

     // Check if customer has orders
     public function isNewCustomer($email)
     {
        if ($email && $email !== '') {
            $orders = Mage::getModel('sales/order')->getCollection()->addFieldToFilter('customer_email', $email);
            return $orders->count() === 0;
        }
        return false;
     }
}
