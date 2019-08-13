<?php

/**
 * This class provides methods which calls on magento dispatch events
 * Class Cartsguru_Model_Observer
 */
class Cartsguru_Model_Observer
{
    /**
     * This method check api available after save config in admin
     * @param $observer
     */
    public function configSaveAfter($observer)
    {
        $session = Mage::getSingleton('core/session');
        return (Mage::getModel('cartsguru/webservice')->checkAddress()) ?
                $session->addSuccess('Connection checked')
                : $session->addError('Error check connection');
    }

    /**
     * This method - hook for order save api call
     * @param $observer
     */
    public function orderSaveAfter($observer)
    {
        $order = $observer->getOrder();
        Mage::getModel('cartsguru/webservice')->sendOrder($order);
    }

    /**
     * This method add token to quote
     * @param $observer
     */
    public function quoteSaveBefore($observer)
    {
        $quote = $observer->getEvent()->getQuote();
        if (!$quote->getData('cartsguru_token')){
            $tools = Mage::helper('cartsguru/tools');
            $quote->setData('cartsguru_token',$tools::generateUUID());
        }
    }

    /**
     * This method - hook for customer save api call
     * @param $observer
     */
    public function customerSaveAfter($observer)
    {
        $customer = $observer->getCustomer();
        Mage::getModel('cartsguru/webservice')->sendAccount($customer);
    }

    /**
     * This method - hook for quote save api call
     * @param $observer
     */
    public function productAddAfter($observer)
    {
        $quote = Mage::getModel('checkout/session')->getQuote();
        Mage::getModel('cartsguru/webservice')->sendAbadonnedCart($quote);
    }
}