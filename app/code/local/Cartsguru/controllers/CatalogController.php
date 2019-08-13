<?php

class Cartsguru_CatalogController extends Mage_Core_Controller_Front_Action {

    public function indexAction()
    {
        $helper = Mage::helper('cartsguru');
        $params = $this->getRequest()->getParams();
        $auth_key = $helper->getStoreConfig('auth');
        // Stop if not authenticated
        if (!isset($params['cartsguru_auth_key']) || $auth_key !== $params['cartsguru_auth_key']) {
            die;
        }
        // Get input values
        $offset = isset($params['cartsguru_catalog_offset']) ? $params['cartsguru_catalog_offset'] : 0;
        $limit = isset($params['cartsguru_catalog_limit']) ? $params['cartsguru_catalog_limit'] : 50;

        $store = Mage::app()->getStore();
        $catalog = Mage::getModel('cartsguru/catalog');

        $this->getResponse()->setHeader('Content-type', 'application/json');
        $this->getResponse()->setBody(json_encode($catalog->generateFeed($store, $offset, $limit)));
    }
}
