<?php

class Cartsguru_Model_Sales_Order_Api_V2 extends Mage_Sales_Model_Order_Api_V2 {

    /**
     * Retrieve full order information
     *
     * @param string $orderIncrementId
     * @return array
     */
    public function info($orderIncrementId)
    {
        $order = $this->_initOrder($orderIncrementId);

        if ($order->getGiftMessageId() > 0) {
            $message = Mage::getSingleton('giftmessage/message')->load($order->getGiftMessageId());
            $order->setGiftMessage($message->getMessage());
        }

        $attributes = $this->_getAttributes($order, 'order');
        $attributes['shipping_address'] = $this->_getAttributes($order->getShippingAddress(), 'order_address');
        $attributes['billing_address']  = $this->_getAttributes($order->getBillingAddress(), 'order_address');
        $attributes['payment'] = $this->_getAttributes($order->getPayment(), 'order_payment');

        $attributes['items'] = array();
        foreach ($order->getAllItems() as $item) {
            if ($item->getGiftMessageId() > 0) {
                $message = Mage::getSingleton('giftmessage/message')->load($item->getGiftMessageId());
                $item->setGiftMessage($message->getMessage());
            }

            $attributes['items'][] = $this->_getAttributes($item, 'order_item');
        }

        $attributes['status_history'] = array();
        foreach ($order->getAllStatusHistory() as $history) {
            $attributes['status_history'][] = $this->_getAttributes($history, 'order_status_history');
        }

        return $attributes;
    }
}