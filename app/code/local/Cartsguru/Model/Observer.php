<?php

/**
 * This class provides methods which calls on magento dispatch events
 * Class Cartsguru_Model_Observer
 */
class Cartsguru_Model_Observer
{
    /**
     * Check api is available after save config in admin
     * @param $observer
     */
    public function configSaveAfter($observer)
    {
        $session = Mage::getSingleton('core/session');
        $webservice = Mage::getModel('cartsguru/webservice');
        
        $result = $webservice->checkAddress();
        
        if ($result == false){
            return $session->addError('Error check connection');
        }
        
        $session->addSuccess('Connection checked');
        
        if ($result->isNew){
            $webservice->sendHistory();
        }
    }

    /**
     * Handle customer data change, and push it to carts guuru
     * @param $observer
     */
    public function customerSaveAfter($observer)
    {
        $customer = $observer->getCustomer();
        Mage::getModel('cartsguru/webservice')->sendAccount($customer);
    }   
    
    /**
     * Set token before quote is save
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
     * Handle quote is saved, and push it to carts guru 
     * @param $observer
     */
    public function quoteSaveAfter($observer)
    {
        $quote = $observer->getEvent()->getQuote();
        Mage::getModel('cartsguru/webservice')->sendAbadonnedCart($quote);
    }

    /**
     * Handle order updated, and push it to carts guru
     * @param $observer
     */
    public function orderSaveAfter($observer) {
        /* @var Mage_Sales_Model_Order $order */
        $order = $observer->getOrder();
        
        // Only trigger when order status change
        if ($order->getStatus() != $order->getOrigData('status')) {
            Mage::getModel('cartsguru/webservice')->sendOrder($order);
        } 
    }    
}