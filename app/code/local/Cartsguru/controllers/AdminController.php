<?php

class Cartsguru_AdminController extends Mage_Core_Controller_Front_Action
{
    public function indexAction()
    {
        $helper = Mage::helper('cartsguru');
        $params = $this->getRequest()->getParams();
        $auth_key = $helper->getStoreConfig('auth');
        // Stop if no enoguth params
        if (!isset($params['cartsguru_admin_action']) || !isset($params['cartsguru_auth_key']) || $auth_key !== $params['cartsguru_auth_key']) {
            die;
        }
        // Toggle features action
        if ($params['cartsguru_admin_action'] === 'toggleFeatures' && isset($params['cartsguru_admin_data'])) {
            $data = json_decode($params['cartsguru_admin_data'], true);
            if (is_array($data)) {
                // Enable facebook
                if ($data['facebook'] && $data['catalogId'] && $data['pixel']) {
                    // Save facebook pixel
                    $helper->setStoreConfig('feature_facebook', true);
                    $helper->setStoreConfig('facebook_pixel', $data['pixel']);
                    $helper->setStoreConfig('facebook_catalogId', $data['catalogId']);
                    // return catalogUrl
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode(array(
                        'catalogUrl' => Mage::app()->getStore($store)->getBaseUrl(Mage_Core_Model_Store::URL_TYPE_LINK) . 'cartsguru/catalog'
                    ));
                } elseif ($data['facebook'] == false) {
                    $helper->setStoreConfig('feature_facebook', false);
                }
            }
        }
        // Get config
        if ($params['cartsguru_admin_action'] === 'displayConfig') {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(array(
                'CARTSG_SITE_ID' => $helper->getStoreConfig('siteid'),
                'CARTSG_FEATURE_FB' => $helper->getStoreConfig('feature_facebook'),
                'CARTSG_FB_PIXEL' => $helper->getStoreConfig('facebook_pixel'),
                'CARTSG_FB_CATALOGID' => $helper->getStoreConfig('facebook_catalogId'),
                'PLUGIN_VERSION'=> (string) Mage::getConfig()->getNode()->modules->Cartsguru->version
            ));
        }
        die;
    }
}
