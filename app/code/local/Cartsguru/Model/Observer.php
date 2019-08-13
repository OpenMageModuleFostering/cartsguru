<?php

/**
 * This class provides methods which calls on magento dispatch events
 * Class Cartsguru_Model_Observer
 */
class Cartsguru_Model_Observer
{
    const LOG_FILE = "cartsguru.log";

    /**
     * Check api is available after save config in admin
     * @param $observer
     */
    public function configSaveAfter($observer)
    {
        Mage::log('Observer: Start handle configSaveAfter', null, Cartsguru_Model_Observer::LOG_FILE);

        $session = Mage::getSingleton('core/session');
        $webservice = Mage::getModel('cartsguru/webservice');

        $result = $webservice->checkAddress();

        if ($result == false) {
            return $session->addError('Error check connection');
        }

        $session->addSuccess('Connection checked');

        if ($result->isNew) {
            $webservice->sendHistory();
        }

        Mage::log('Observer: End handle configSaveAfter', null, Cartsguru_Model_Observer::LOG_FILE);
    }

    /**
     * Handle customer data change, and push it to carts guuru
     * @param $observer
     */
    public function customerSaveAfter($observer)
    {
        Mage::log('Observer: Start handle customerSaveAfter', null, Cartsguru_Model_Observer::LOG_FILE);

        $customer = $observer->getCustomer();
        Mage::getModel('cartsguru/webservice')->sendAccount($customer);

        Mage::log('Observer: End handle customerSaveAfter', null, Cartsguru_Model_Observer::LOG_FILE);
    }

    /**
     * Set token before quote is save
     * @param $observer
     */
    public function quoteSaveBefore($observer)
    {
        $quote = $observer->getEvent()->getQuote();

        Mage::log('Observer: Start handle quoteSaveBefore for ' . $quote->getId(), null, Cartsguru_Model_Observer::LOG_FILE);

        if (!$quote->getData('cartsguru_token')) {
            $tools = Mage::helper('cartsguru/tools');
            $quote->setData('cartsguru_token', $tools::generateUUID());
        }

        Mage::log('Observer: End handle quoteSaveBefore for ' . $quote->getId(), null, Cartsguru_Model_Observer::LOG_FILE);
    }

    /**
     * Handle quote is saved, and push it to carts guru
     * @param $observer
     */
    public function quoteSaveAfter($observer)
    {
        $quote = $observer->getEvent()->getQuote();

        Mage::log('Observer: Start handle quoteSaveAfter for ' . $quote->getId(), null, Cartsguru_Model_Observer::LOG_FILE);

        Mage::getModel('cartsguru/webservice')->sendAbadonnedCart($quote);

        Mage::log('Observer: End handle quoteSaveAfter for ' . $quote->getId(), null, Cartsguru_Model_Observer::LOG_FILE);
    }

    /**
     * Handle order updated, and push it to carts guru
     * @param $observer
     */
    public function orderSaveAfter($observer)
    {
        /* @var Mage_Sales_Model_Order $order */
        $order = $observer->getOrder();


        Mage::log('Observer: Start handle orderSaveAfter for ' . $order->getIncrementId(), null, Cartsguru_Model_Observer::LOG_FILE);

        // Only trigger when order status change
        if ($order->getStatus() != $order->getOrigData('status')) {
            Mage::getModel('cartsguru/webservice')->sendOrder($order);
        }

        Mage::log('Observer: End handle orderSaveAfter for ' . $order->getIncrementId(), null, Cartsguru_Model_Observer::LOG_FILE);
    }

    /**
     * Saves added product data to fire FB pixel
     * @param $observer
     */
    public function checkoutCartAdd($observer)
    {
        $helper = Mage::helper('cartsguru');
        $facebook_enabled = $helper->getStoreConfig("feature_facebook");
        if ($facebook_enabled) {
            // Laod product
            $product = Mage::getModel('catalog/product')
                ->load(Mage::app()->getRequest()->getParam('product', 0));

            if (!$product->getId()) {
                return;
            }
            $price = $product->getPrice();
            if (!$price) {
                $price = $product->getSpecialPrice();
            }
            // Save product data into session
            Mage::getSingleton('core/session')->setCartsGuruAddToCart(
                new Varien_Object(array(
                    'id' => $product->getId(),
                    'price' => number_format((double)$price, 2, '.', ',')
                ))
            );
        }
    }

    /**
     * Check if we have source query param ans set the cookie
     * @param $observer
     */
    public function checkSource($observer)
    {
        $utm_source = Mage::app()->getRequest()->getParam('utm_source');
        $utm_campaign = Mage::app()->getRequest()->getParam('utm_campaign');
        if ($utm_source && $utm_campaign) {
            if (!empty($utm_source) && $utm_source === 'cartsguru-fb' && !empty($utm_campaign)) {
                $cookie = Mage::getSingleton('core/cookie');
                $ttl = 60 * 60 * 24 * 30; // 1 month
                $cookie->set('cartsguru-source', serialize(array(
                    'type' => $utm_source,
                    'campaign' => $utm_campaign,
                    'timestamp' => time()
                )), $ttl, '/');
            }
        }
    }
}
