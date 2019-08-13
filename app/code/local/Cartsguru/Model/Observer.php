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
        Mage::getModel('cartsguru/webservice')->sendOrder($observer->getOrder());
    }

    /**
     * This method add telephone and country code to quote
     * @param $observer
     */
    public function quoteSaveBefore($observer)
    {
        $quote = $observer->getQuote();
        $request = Mage::app()->getRequest()->getParams();
        $cache = Mage::app()->getCache();

        if (isset($request['billing'])) {
            if (isset($request['billing']['telephone'])) {
                $quote->setTelephone($request['billing']['telephone']);
            }

            if (isset($request['billing']['country_id'])) {
                $quote->setData('country', $request['billing']['country_id']);
            }
        }
    }

    /**
     * This method - hook for customer save api call
     * @param $observer
     */
    public function customerSaveAfter($observer)
    {
        Mage::getModel('cartsguru/webservice')->sendAccount($observer->getCustomer());
    }

    public function productAddAfter($observer)
    {
        $quote = Mage::getModel('checkout/session')->getQuote();
        $customer = $quote->getCustomer();
        if (isset($customer)) {
            $billingAddress = $customer->getDefaultBillingAddress();
            if ($billingAddress) {
                if (empty($quote->getTelephone())) {
                    $quote->setTelephone($billingAddress->getTelephone());
                }
                if (empty($quote->getCountry())) {
                    $quote->setData('country', $billingAddress->getCountryId());
                }
            }
        }

        Mage::getModel('cartsguru/webservice')->sendAbadonnedCart($quote);
    }

    /**
     * This method set telephone in session
     */
    public function setTelephoneInSession()
    {
        $telephone = Mage::app()->getRequest()->getParams()['billing']['telephone'];
        Mage::getSingleton("core/session")->setData("telephone", $telephone);
    }
}