<?php

class Cartsguru_RecovercartController extends Mage_Core_Controller_Front_Action {    
    
    private function redirectToCart() {
        $url = Mage::helper('checkout/cart')->getCartUrl();
        $this->getResponse()->setRedirect($url)->sendResponse();
    }
    
    public function indexAction(){
        // Get request params
        $params = $this->getRequest()->getParams();
        
        // Stop if no enoguth params
        if (!isset($params['cart_id']) || !isset($params['cart_token'])){
            return redirectToCart();
        }
        
        // Load quote by id
        $quote = Mage::getModel('sales/quote')->load($params['cart_id']);

        // Stop if quote does not exist
        if (!$quote->getId()){
            return redirectToCart();
        }
        
        // Check quote token
        $token = $quote->getData('cartsguru_token');
        if (!$token || $token != $params['cart_token']){
            return redirectToCart();
        }
        
        // Auto log customer if we can
        if ($quote->getCustomerId()){
            //Gest customer
            $customer = Mage::getModel('customer/customer')->load($quote->getCustomerId());
             
            Mage::getSingleton('customer/session')->setCustomerAsLoggedIn($customer);
        }
        else {
            // Get current cart 
            $cart = Mage::getSingleton('checkout/cart');
            
            
            foreach ($cart->getQuote()->getAllVisibleItems() as $item) {
                $found = false;
                foreach ($quote->getAllItems() as $quoteItem) {
                    if ($quoteItem->compare($item)) {
                      //  $quoteItem->setQty($item->getQty());
                        $found = true;
                        break;
                    }
                }
    
                if (!$found) {
                    $newItem = clone $item;
                    $quote->addItem($newItem);
                    if ($quote->getHasChildren()) {
                        foreach ($item->getChildren() as $child) {
                            $newChild = clone $child;
                            $newChild->setParentItem($newItem);
                            $quote->addItem($newChild);
                        }
                    }
                }
            }
            
            $quote->save();
            $cart->setQuote($quote);
            $cart->init();
            $cart->save();
        }
        
        // Redirect to checkout
        return redirectToCart();
    }
}