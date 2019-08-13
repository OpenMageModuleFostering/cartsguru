<?php

/**
* Class of Magento core helper abstraction
* Class Cartsguru_Helper_Data
*/
class Cartsguru_Helper_Data extends Mage_Core_Helper_Abstract
{
    private $configBasePath = 'cartsguru/cartsguru_group/';

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
    public function getCustomerGroupName($email)
    {
        $groupName = 'not logged in';
        if (Mage::getSingleton('customer/session')->isLoggedIn()) {
            $groupId = Mage::getSingleton('customer/session')->getCustomerGroupId();
            $groupName = Mage::getSingleton('customer/group')->load($groupId)->getData('customer_group_code');
        } elseif ($email && $email !== '') {
            $customer = Mage::getModel("customer/customer");
            $customer->setWebsiteId(Mage::app()->getStore()->getWebsiteId());
            $customer->loadByEmail($email);
            if ($customer) {
                $groupId = $customer->getGroupId();
                $groupName = Mage::getSingleton('customer/group')->load($groupId)->getData('customer_group_code');
            }
        }
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

    // Get store from admin
    public function getStoreFromAdmin()
    {
        $store_id = null;
        if (strlen($code = Mage::getSingleton('adminhtml/config_data')->getStore())) { // store level
            $store_id = Mage::getModel('core/store')->load($code)->getId();
        } elseif (strlen($code = Mage::getSingleton('adminhtml/config_data')->getWebsite())) { // website level
            $website_id = Mage::getModel('core/website')->load($code)->getId();
            $store_id = Mage::app()->getWebsite($website_id)->getDefaultStore()->getId();
        } elseif (strlen($code = Mage::app()->getRequest()->getParam('website'))) {
            $website_id = Mage::getModel('core/website')->load($code)->getId();
            $store_id = Mage::app()->getWebsite($website_id)->getDefaultStore()->getId();
        }

        if ($store_id) {
            return Mage::app()->getStore($store_id);
        } else {
            return Mage::app()->getStore();
        }
    }

    // Save config in store
    public function setStoreConfig($key, $value, $store = null)
    {
        if (!$store) {
            $store = Mage::app()->getStore();
        }

        Mage::getConfig()->saveConfig($this->configBasePath . $key, $value, 'stores', $store->getStoreId());
    }
    // Get store config
    public function getStoreConfig($key, $store = null)
    {
        if (!$store) {
            $store = Mage::app()->getStore();
        }

        return $store->getConfig($this->configBasePath . $key);
    }
}
