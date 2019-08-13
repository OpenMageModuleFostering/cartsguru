<?php

class Cartsguru_AdminController extends Mage_Core_Controller_Front_Action {

    public function indexAction(){
        $params = $this->getRequest()->getParams();
        $webservice = Mage::getModel('cartsguru/webservice');
        $store = Mage::app()->getWebsite(true)->getDefaultGroup()->getDefaultStore();
        $auth_key = $webservice->getStoreConfig('auth', $store);
        // Stop if no enoguth params
        if (!isset($params['cartsguru_admin_action']) || !isset($params['cartsguru_auth_key']) || $auth_key !== $params['cartsguru_auth_key']){
            die;
        }
        // Toggle features action
        if ($params['cartsguru_admin_action'] === 'toggleFeatures' && isset($params['cartsguru_admin_data'])) {
            $data = json_decode($params['cartsguru_admin_data'], true);
            if (is_array($data)) {
                // Enable facebook
                if ($data['facebook'] && $data['catalogId'] && $data['pixel']) {
                    // Save facebook pixel
                    Mage::getConfig()->saveConfig('cartsguru/cartsguru_group/feature_facebook', true);
                    Mage::getConfig()->saveConfig('cartsguru/cartsguru_group/facebook_pixel', $data['pixel']);
                    Mage::getConfig()->saveConfig('cartsguru/cartsguru_group/facebook_catalogId', $data['catalogId']);
                    // return catalogUrl
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode(array('catalogUrl' => Mage::getBaseUrl() . 'cartsguru/catalog'));
                    die;
                } elseif ($data['facebook'] == false) {
                    Mage::getConfig()->saveConfig('cartsguru/cartsguru_group/feature_facebook', false);
                }
            }
        }
    }
}
