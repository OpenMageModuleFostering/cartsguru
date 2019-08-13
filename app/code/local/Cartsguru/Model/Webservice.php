<?php

/**
 * This class using to get api answers
 * Class Cartsguru_Model_Webservice
 */
class Cartsguru_Model_Webservice
{
    private $apiBaseUrl = 'https://api.carts.guru';

    /**
     * If value is empty return ''
     * @param $value
     * @return string
     */
    public function notEmpty($value)
    {
        return ($value)? $value : '';
    }

    /**
     * This method return order data in cartsguru format
     * @param $order
     * @return array
     */
    public function getOrderData($order)
    {
        $items = array();
        foreach ($order->getAllVisibleItems() as $item) {
            $product=$item->getProduct();
            $product=$product->load($product->getId());
            if($product->getImage() && $product->getImage() != 'no_selection') {
                $imageUrl=(string)Mage::helper('catalog/image')->init($item->getProduct(), 'small_image')
                    ->constrainOnly(false)
                    ->keepAspectRatio(true)
                    ->keepFrame(true)
                    ->resize(120, 120);
            }
            else {
                $imageUrl=$this->notEmpty(null);
            }
            $categoryNames = $this->getCatNames($item);
            $items[] = array(
                'id'        => $item->getId(),                          // SKU or product id
                'label'     => $item->getName(),                        // Designation
                'quantity'  => (int)$item->getQtyOrdered(),             // Count
                'totalET'   => (float)$item->getPrice()*(int)$item->getQtyOrdered(), // Subtotal of item
                'url'       => $item->getProduct()->getProductUrl(),    // URL of product sheet
                'imageUrl'  => $imageUrl,
                'universe'  => $categoryNames[1],
                'category'  => end($categoryNames)
            );
        }

        $gender = 'mister';
        $customer = $order->getCustomer();
        if ($customer && $customer->getGender()) {
            $gender = $customer->getGender();
        }

        $phone = $order->getTelephone();
        $country = $order->getCountry();
        if (!$accountId = $order->getCustomerId()) {
            $accountId = $order->getCustomerEmail();
        }

        return array(
            'siteId'        => Mage::getStoreConfig('cartsguru/cartsguru_group/siteid', Mage::app()->getStore()),   //Site Id
            'id'            => $order->getIncrementId(),                                        //Order reference, the same display to the buyer
            'creationDate'  => $this->formatDate($order->getCreatedAt()),                       // Date of the order as string in json format
            'cartId'        => $order->getQuoteId(),                                            // Cart identifier, source of the order
            'totalET'       => (float)$order->getSubtotal(),                                  // Amount excluded taxes and excluded shipping
            'state'         => $this->getStatus($order->getStatus()),                           // waiting, confirmed, cancelled or error
            'accountId'     => $accountId,                                                      // Account id of the buyer
            'ip'            => $order->getRemoteIp(),
            'civility'      => $gender,                                                         // Use string in this list : 'mister','madam','miss'
            'lastname'      => $this->notEmpty($order->getBillingAddress()->getLastname()),     // Lastname of the buyer
            'firstname'     => $this->notEmpty($order->getBillingAddress()->getFirstname()),    // Firstname of the buyer
            'email'         => $this->notEmpty($order->getCustomerEmail()),                     // Email of the buye
            'phoneNumber'   => $this->notEmpty($phone),                                         // Landline phone number of buyer (internationnal format)
            'countryCode'   => $this->notEmpty($country),                                       // Country code of buyer
            'items'         => $items                                                           // Details info
        );
    }

    /**
     * This method send order data by api
     * @param $order
     */
    public function sendOrder($order)
    {
        $orderData = $this->getOrderData($order);
        if (!empty($orderData)) {
            $this->doPostRequest('/orders', $orderData);
        }
    }

    /**
     * This method format date in json format
     * @param $date
     * @return bool|string
     */
    protected function formatDate($date)
    {
        return date('Y-m-d\TH:i:sP', strtotime($date));
    }

    /**
     * This method send data on api path
     * @param $apiPath
     * @param $fields
     * @return Zend_Http_Response
     */
    protected function doPostRequest($apiPath, $fields)
    {
        try {
            $url = $this->apiBaseUrl . $apiPath;
            $client = new Zend_Http_Client($url);
            $client->setHeaders('x-auth-key', Mage::getStoreConfig('cartsguru/cartsguru_group/auth', Mage::app()->getStore()));
            $client->setUri($url);
            $client->setRawData(json_encode($fields), 'application/json');
            $response = $client->request(Zend_Http_Client::POST);
        } catch (Exception $e) {}

        return $response;
    }

    /**
     * This method map magento status to api status
     * @param $status
     * @return string
     */
    public function getStatus($status)
    {
        $status_map = array(
            'processing'                => 'confirmed',
            'pending'                   => 'confirmed',
            'pending_payment'           => 'waiting',
            'waiting_authorozation'     => 'waiting',
            'payment_review'            => 'confirmed',
            'fraud'                     => 'paymentFailure',
            'holded'                    => 'error',
            'complete'                  => 'confirmed',
            'closed'                    => 'confirmed',
            'canceled'                  => 'cancelled',            
            'pending_ogone'             => 'waiting',
            'processing_ogone'          => 'confirmed',
            'decline_ogone'             => 'paymentFailure',
            'cancel_ogone'              => 'paymentFailure',
            'pending_paypal'            => 'waiting',
            'paypal_canceled_reversal'  => 'paymentFailure',
            'paypal_reversed'           => 'paymentFailure',
        );

        return isset($status_map[$status])?
                $status_map[$status]
                : $status;
    }

    /** This method return true if connect to server is ok
     * @return bool
     */
    public function checkAddress()
    {
        $baseUrl = Mage::getBaseUrl() . 'api/rest';
        $fields = array(
            'plugin'                => 'magento',
            'pluginVersion'         => '1.1.0',
            'storeVersion'          => Mage::getVersion()
        );
        $siteId = Mage::getStoreConfig('cartsguru/cartsguru_group/siteid', Mage::app()->getStore());
        $requestUrl = '/sites/' . $siteId . '/register-plugin';

        $response = $this->doPostRequest($requestUrl, $fields);
        return ($response)?
                ($response->getStatus() == 200)
                : false;
    }

    /**
     * Get category names
     * @param $item
     * @return array
     */
    public function getCatNames($item)
    {
        $product = $item->getProduct();
        $categoryNames = array();
        $categoryIds = $product->getCategoryIds();
        foreach ($categoryIds as $categoryId) {
            $category = Mage::getModel('catalog/category')->load($categoryId);
            $ids = explode('/', $category->getPath());
            foreach ($ids as $id) {
                $category = Mage::getModel('catalog/category')->load($id);
                $categoryNames[] = $category->getName();
            }
        }

        if (empty($categoryNames)) {
            $categoryNames = array(
                0 => $this->notEmpty(null),
                1 => $this->notEmpty(null)
            );
        }

        return $categoryNames;
    }

    /**
     * This method return abounded cart data in cartsguru api format
     * @param $quote
     * @return array|void
     */
    public function getAbadonnedCartData($quote)
    {
        $items = array();
        /** @var Mage_Sales_Model_Quote $quote */
        foreach ($quote->getAllVisibleItems() as $item) {
            $product=$item->getProduct();
            $product=$product->load($product->getId());
            if($product->getImage() && $product->getImage() != 'no_selection') {
                $imageUrl=(string)Mage::helper('catalog/image')->init($item->getProduct(), 'small_image')
                    ->constrainOnly(false)
                    ->keepAspectRatio(true)
                    ->keepFrame(true)
                    ->resize(120, 120);
            }
            else {
                $imageUrl=$this->notEmpty(null);
            }
            $categoryNames = $this->getCatNames($item);
            $items[] = array(
                'id'        => (string)$item->getProduct()->getSku(),   // SKU or product id
                'label'     => $item->getName(),                        // Designation
                'quantity'  => (int)$item->getQty(),                    // Count
                'totalET'   => (float)$item->getPrice()*(int)$item->getQty(), // Subtotal of item
                'url'       => $item->getProduct()->getProductUrl(),    // URL of product sheet
                'imageUrl'  => $imageUrl,
                'universe'  => $categoryNames[1],
                'category'  => end($categoryNames)
            );
        }

        $gender = 'mister';
        if ($quote->getCustomerGender()) {
            $gender = $quote->getCustomerGender();
        }

        $lastname = $quote->getCustomerLastname();
        $firstname = $quote->getCustomerFirstname();

        $phone = $quote->getTelephone();
        $country = $quote->getCountry();

        if (!$items) {
            return;
        }

        if (!$accountId = $quote->getCustomerId()) {
            $accountId = $quote->getCustomerEmail();
        }

        if (!$accountId && !$phone) {
            return;
        }

        $siteId = Mage::getStoreConfig('cartsguru/cartsguru_group/siteid', Mage::app()->getStore());
        
        return array(
            'siteId'        => $siteId,                                         //SiteId is part of plugin configuration
            'id'            => $quote->getId(),                                 //Order reference, the same display to the buyer
            'creationDate'  => $this->formatDate($quote->getCreatedAt()),       // Date of the order as string in json format
            'totalET'       => (float)$quote->getSubtotal(),                       // Amount excluded taxes and excluded shipping
            'accountId'     => $accountId,                                      // Account id of the buyer
            'civility'      => $gender,                                         // Use string in this list : 'mister','madam','miss'
            'ip'            => $quote->getRemoteIp(),
            'lastname'      => $this->notEmpty($lastname),                      // Lastname of the buyer
            'firstname'     => $this->notEmpty($firstname),                     // Firstname of the buyer
            'email'         => $this->notEmpty($quote->getCustomerEmail()),                     // Email of the buyer
            'phoneNumber'   => $this->notEmpty($phone),                         // Landline phone number of buyer (internationnal format)
            'countryCode'   => $this->notEmpty($country),
            'items'         => $items
        );
    }

    /**
     * This method send abounded cart data
     * @param $quote
     */
    public function sendAbadonnedCart($quote)
    {
        $cartData = $this->getAbadonnedCartData($quote);
        $cache = Mage::app()->getCache();
        $data = $cache->load(md5(json_encode($cartData)));
        if (empty($data)) {
            $cache->save(serialize($cartData), md5(json_encode($cartData)));
            if ($cartData) {
                $this->doPostRequest('/carts', $cartData);
            }
        }
    }

    /**
     * get customer Firstname
     * @param $customer
     * @return string
     */
    public function getFirstname($customer)
    {
        $firstname = $customer->getFirstname();
        if (!$firstname) {
            $address = $customer->getDefaultBillingAddress();
            if ($address) {
                return $address->getFirstname();
            }
        }

        return $firstname;
    }

    /**
     * get customer Lastname
     * @param $customer
     * @return string
     */
    public function getLastname($customer)
    {
        $lastname = $customer->getLastname();
        if (!$lastname) {
            $address = $customer->getDefaultBillingAddress();
            if ($address) {
                return $address->getLastname();
            }
        }

        return $lastname;
    }

    /**
     * get customer gender and format it
     * @param $customer
     * @return string
     */
    public function getGender($customer)
    {
        return ($customer->getGender())?
                $customer->getGender()
                : 'mister';
    }

    /**
     * get customer Phone
     * @param $customer
     * @return string
     */
    public function getPhone($customer)
    {
        $address = $customer->getDefaultBillingAddress();
        return ($address)?
                $address->getPhone()
                : '';
    }

    /**
     * This method get customer data in cartsguru api format
     * @param $customer
     * @return array
     */
    public function getCustomerData($customer)
    {
        $gender = $this->getGender($customer);
        $lastname = $this->getLastname($customer);
        $firstname = $this->getFirstname($customer);
        $phone = '';
        $country = '';
        $address = Mage::getModel('customer/address')->load($customer->getDefaultBillingAddress());
        if ($address) {
            $phone = $address->getTelephone();
            $country = $address->getCountryId();
        }

        $siteId = Mage::getStoreConfig('cartsguru/cartsguru_group/siteid', Mage::app()->getStore());
        return array(
            'siteId'        => $siteId,                                 //SiteId is part of plugin configuration
            'accountId'     => $customer->getId(),                      // Account id of the customer
            'civility'      => $gender,                                 // Use string in this list : 'mister','madam','miss'
            'lastname'      => $this->notEmpty($lastname),              // Lastname of the buyer
            'firstname'     => $this->notEmpty($firstname),             // Firstname of the buyer
            'email'         => $this->notEmpty($customer->getEmail()),  // Email of the customer
            'phoneNumber'   => $this->notEmpty($phone),                 // Landline phone number of buyer (internationnal format)
            'countryCode'   => $this->notEmpty($country)
        );
    }

    /**
     * This method send customer data on api
     * @param $customer
     */
    public function sendAccount($customer)
    {
        $customerData = $this->getCustomerData($customer);
        $this->doPostRequest('/accounts', $customerData);
    }
}