<?php

class Cartsguru_SaveaccountController extends Mage_Core_Controller_Front_Action {

    public function indexAction() {
        // Get request params
        $params = $this->getRequest()->getParams();

        // Stop if no email
        if (!isset($params['email'])){
            return;
        }
        // Post the data
        $quote = Mage::getModel('checkout/cart')->getQuote();
        $quote->setCustomerEmail($params['email']);
        if (isset($params['firstname'])){
            $quote->setCustomerFirstname($params['firstname']);
        }
        if (isset($params['lastname'])){
            $quote->setCustomerLastname($params['lastname']);
        }
        $address = $quote->getBillingAddress();
        if ($address) {
            if (isset($params['telephone'])) {
                $address->setTelephone($params['telephone']);
            }
            if (isset($params['country'])) {
                $address->setCountryId($params['country']);
            }
            $quote->setBillingAddress($address);
        }
        $webservice = Mage::getModel('cartsguru/webservice');
        $webservice->sendAbadonnedCart($quote);
    }
}
