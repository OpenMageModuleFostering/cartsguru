<?php

/**
 * This class using to get api answers
 * Class Cartsguru_Model_Webservice
 */
class Cartsguru_Model_Webservice
{
    private $apiBaseUrl = 'https://api.carts.guru';
    private $configBasePath = 'cartsguru/cartsguru_group/';
    private $historySize = 150;

    protected function getStoreFromAdmin(){
        $store_id;
        if (strlen($code = Mage::getSingleton('adminhtml/config_data')->getStore())) // store level
        {
            $store_id = Mage::getModel('core/store')->load($code)->getId();
        }
        elseif (strlen($code = Mage::getSingleton('adminhtml/config_data')->getWebsite())) // website level
        {
            $website_id = Mage::getModel('core/website')->load($code)->getId();
            $store_id = Mage::app()->getWebsite($website_id)->getDefaultStore()->getId();
        }
        elseif (strlen($code = Mage::app()->getRequest()->getParam('website'))) {
            $website_id = Mage::getModel('core/website')->load($code)->getId();
            $store_id = Mage::app()->getWebsite($website_id)->getDefaultStore()->getId(); 
        }
        
        if ($store_id){
            return Mage::app()->getStore($store_id);
        }
        else {
            return Mage::app()->getStore();
        }
    }
    
    protected function setStoreConfig($key, $value, $store = null)
    {
        if (!$store){
            $store = Mage::app()->getStore();
        }
        
        $store->setConfig($this->configBasePath . $key, $value);
    }
    
    protected function getStoreConfig($key, $store = null){
        if (!$store){
            $store = Mage::app()->getStore();
        }
        
        return $store->getConfig($this->configBasePath . $key);
    }
    
    protected function isStoreConfigured(){
        return $this->getStoreConfig('siteid') && $this->getStoreConfig('auth');
    }
    
    /**
     * If value is empty return ''
     * @param $value
     * @return string
     */
    protected function notEmpty($value)
    {
        return ($value)? $value : '';
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
     * Get category names
     * @param $item
     * @return array
     */
    public function getCatNames($product)
    {
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
     * This method calculate total taxes included, shipping excluded
     * @param $obj order or quote
     * @return float
     */
    public function getTotalATI($items){
        $totalATI = (float)0;
        
        foreach ($items as $item) {
            $totalATI += $item['totalATI'];
        }
        
        return $totalATI;
    }
        
    /**
     * This method build items from order or quote
     * @param $obj order or quote
     * @return array
     */
    public function getItemsData($obj){
        $items = array();
        foreach ($obj->getAllVisibleItems() as $item) {
            $product = Mage::getModel('catalog/product')->load($item->getProductId());
            
            if ($product->getImage() == 'no_selection' || !$product->getImage()){
                $imageUrl = $this->notEmpty(null);
            }
            else {
                $imageUrl = $product->getSmallImageUrl(120, 120);
            }
            
            $categoryNames = $this->getCatNames($product);
            
            $quantity = (int)$item->getQtyOrdered();
            if ($quantity == 0){
                $quantity = (int)$item->getQty();
            }
            
            $items[] = array(
                'id'        => $item->getId(),                          // SKU or product id
                'label'     => $item->getName(),                        // Designation
                'quantity'  => $quantity,                               // Count
                'totalET'   => (float)$item->getPrice()*$quantity, // Subtotal of item, taxe excluded
                'totalATI'  => (float)$item->getPriceInclTax()*$quantity, // Subtotal of item, taxe included
                'url'       => $product->getProductUrl(),    // URL of product sheet
                'imageUrl'  => $imageUrl,
                'universe'  => $this->notEmpty($categoryNames[1]),
                'category'  => $this->notEmpty(end($categoryNames))
            );
        }
        return $items;
    }
    
    /**
     * This method return order data in cartsguru format
     * @param $order
     * @return array
     */
    public function getOrderData($order, $store = null)
    {
        //Order must have a status
        if (!$order->getStatus()){
            return null;
        }
        
        //Customer data
        $gender = $this->genderMapping($order->getCustomerGender());
        $email = $order->getCustomerEmail();
        
        //Address
        $address = $order->getBillingAddress();
        
        //Items details
        $items = $this->getItemsData($order);

        return array(
            'siteId'        => $this->getStoreConfig('siteid', $store),                         // SiteId is part of plugin configuration
            'id'            => $order->getIncrementId(),                                        // Order reference, the same display to the buyer
            'creationDate'  => $this->formatDate($order->getCreatedAt()),                       // Date of the order as string in json format
            'cartId'        => $order->getQuoteId(),                                            // Cart identifier, source of the order
            'totalET'       => (float)$order->getSubtotal(),                                    // Amount excluded taxes and excluded shipping
            'totalATI'      => (float)$order->getGrandTotal(),                                  // Paid amount
            'currency'      => $order->getOrderCurrencyCode(),                                  // Currency as USD, EUR
            'paymentMethod' => $order->getPayment()->getMethodInstance()->getTitle(),           // Payment method label
            'state'         => $order->getStatus(),                                             // raw order status
            'accountId'     => $email,                                                          // Account id of the buyer
            'ip'            => $order->getRemoteIp(),                                           // User IP
            'civility'      => $this->notEmpty($gender),                                        // Use string in this list : 'mister','madam','miss'
            'lastname'      => $this->notEmpty($address->getLastname()),                        // Lastname of the buyer
            'firstname'     => $this->notEmpty($address->getFirstname()),                       // Firstname of the buyer
            'email'         => $this->notEmpty($email),                                         // Email of the buye
            'phoneNumber'   => $this->notEmpty($address->getTelephone()),                       // Landline phone number of buyer (internationnal format)
            'countryCode'   => $this->notEmpty($address->getCountryId()),                       // Country code of buyer
            'items'         => $items                                                           // Details
        );
    }

    /**
     * This method send order data by api
     * @param $order
     */
    public function sendOrder($order)
    {
        //Check is well configured
        if (!$this->isStoreConfigured()){
            return;
        }
        
        //Get data, stop if none
        $orderData = $this->getOrderData($order);
        if (empty($orderData)) {
            return;
        }
        
        //Push data to api
        $this->doPostRequest('/orders', $orderData);
    }   
    
    /**
     * Map int of geder to string
     * @param $gender
     * @return string
     */
    public function genderMapping($gender){
        switch((int)$gender){
            case 1: 
                return 'mister';
            case 2:
                return 'madam';
            default:
                return '';
        }
    }
    
    /**
     * This method return abounded cart data in cartsguru api format
     * @param $quote
     * @return array|void
     */
    public function getAbadonnedCartData($quote, $store = null)
    {
        //Customer data
        $gender = $this->genderMapping($quote->getCustomerGender());

        $lastname = $quote->getCustomerLastname();
        $firstname = $quote->getCustomerFirstname();
        $email = $quote->getCustomerEmail();
        
        //Lookup for phone & country
        $customer = $quote->getCustomer();
        $address = $quote->getBillingAddress();
        $request = Mage::app()->getRequest()->getParams();
        $phone = '';
        $country = '';
        
        if (isset($request['billing'])) {
            if (isset($request['billing']['telephone'])) {
                $phone = $request['billing']['telephone'];
            }

            if (isset($request['billing']['country_id'])) {
                $country = $request['billing']['country_id'];
            }
        }
        
        if ($address){
            if (!$phone){
                $phone = $address->getTelephone();
            }
            if (!$country){
                $country = $address->getCountryId();
            }
        }
        
        if ($customer){
            $customerAddress = $customer->getDefaultBillingAddress();
            
            if ($customerAddress && !$phone){
                $phone = $customerAddress->getTelephone();    
            }
            if ($customerAddress && !$country){
                $country = $customerAddress->getCountryId();
            }
        }
        
        //Recover link
        $recoverUrl = ($quote->getData('cartsguru_token')) ?
                        Mage::getBaseUrl() . 'cartsguru/recovercart?cart_id=' . $quote->getId() . '&cart_token=' . $quote->getData('cartsguru_token') :
                        '';
        
        //Items details
        $items = $this->getItemsData($quote);

        //Check is valid
        if (!$email || sizeof($items) == 0) {
            return;
        }
        
        return array(
            'siteId'        => $this->getStoreConfig('siteid', $store),         // SiteId is part of plugin configuration
            'id'            => $quote->getId(),                                 // Order reference, the same display to the buyer
            'creationDate'  => $this->formatDate($quote->getCreatedAt()),       // Date of the order as string in json format
            'totalET'       => (float)$quote->getSubtotal(),                    // Amount excluded taxes and excluded shipping
            'totalATI'      => $this->getTotalATI($items),                      // Amount included taxes and excluded shipping
            'currency'      => $quote->getQuoteCurrencyCode(),                  // Currency as USD, EUR
            'ip'            => $quote->getRemoteIp(),                           // User IP
            'accountId'     => $email,                                          // Account id of the buyer
            'civility'      => $gender,                                         // Use string in this list : 'mister','madam','miss'
            'lastname'      => $this->notEmpty($lastname),                      // Lastname of the buyer
            'firstname'     => $this->notEmpty($firstname),                     // Firstname of the buyer
            'email'         => $this->notEmpty($email),                         // Email of the buyer
            'phoneNumber'   => $this->notEmpty($phone),                         // Landline phone number of buyer (internationnal format)
            'countryCode'   => $this->notEmpty($country),                       // Country code of the buyer
            'recoverUrl'    => $recoverUrl,                                     // Direct link to recover the cart
            'items'         => $items                                           // Details
        );
    }

    /**
     * This method send abounded cart data
     * @param $quote
     */
    public function sendAbadonnedCart($quote, $isImport = false)
    {
        //Check is well configured
        if (!$this->isStoreConfigured()){
            return;
        }
        
        //Get data and continue only if exist
        $cartData = $this->getAbadonnedCartData($quote);
        if (!$cartData){
            return;
        }
        
        if (!$isImport){
            //Check not already sent
            $cache = Mage::app()->getCache();
            $cacheId = 'cg-quote-' . $quote->getId();
            $cachedMd5 = $cache->load($cacheId);
            $dataMd5 = md5(json_encode($cartData));

            if ($dataMd5 == $cachedMd5) {
                return;
            }

            $cache->save($dataMd5, $cacheId);
        }

        //Set special if import
        $route = $isImport ? '/import/carts' : '/carts';
            
        $this->doPostRequest($route, $cartData);
    }
    
    /**
     * Get customer Firstname
     * @param $customer
     * @return string
     */
    public function getFirstname($customer, $address)
    {
        $firstname = $customer->getFirstname();
        if (!$firstname && $address) {
            $firstname = $address->getFirstname();
        }

        return $firstname;
    }

    /**
     * Get customer Lastname
     * @param $customer
     * @return string
     */
    public function getLastname($customer, $address)
    {
        $lastname = $customer->getLastname();
        if (!$lastname && $address) {
            $lastname = $address->getLastname();
        }

        return $lastname;
    }

    /**
     * This method get customer data in cartsguru api format
     * @param $customer
     * @return array
     */
    public function getCustomerData($customer, $store = null)
    {
        $address = $customer->getDefaultBillingAddress();
        
        $gender = $this->genderMapping($customer->getGender());
        $lastname = $this->getLastname($customer, $address);
        $firstname = $this->getFirstname($customer, $address);
        $phone = '';
        $country = '';
        if ($address) {
            $phone = $address->getTelephone();
            $country = $address->getCountryId();
        }

        return array(
            'siteId'        => $this->getStoreConfig('siteid', $store),     // SiteId is part of plugin configuration
            'accountId'     => $customer->getId(),                          // Account id of the customer
            'civility'      => $gender,                                     // Use string in this list : 'mister','madam','miss'
            'lastname'      => $this->notEmpty($lastname),                  // Lastname of the buyer
            'firstname'     => $this->notEmpty($firstname),                 // Firstname of the buyer
            'email'         => $this->notEmpty($customer->getEmail()),      // Email of the customer
            'phoneNumber'   => $this->notEmpty($phone),                     // Landline phone number of buyer (internationnal format)
            'countryCode'   => $this->notEmpty($country)
        );
    }

    /**
     * This method send customer data on api
     * @param $customer
     */
    public function sendAccount($customer)
    {
        //Check is well configured
        if (!$this->isStoreConfigured()){
            return;
        }
        
        $customerData = $this->getCustomerData($customer);
        $this->doPostRequest('/accounts', $customerData);
    }
    


    /** This method return true if connect to server is ok
     * @return bool
     */
    public function checkAddress()
    {
        $store = $this->getStoreFromAdmin();
        
        $requestUrl = '/sites/' . $this->getStoreConfig('siteid', $store) . '/register-plugin';
        $fields = array(
            'plugin'                => 'magento',
            'pluginVersion'         => '1.2.6',
            'storeVersion'          => Mage::getVersion()
        );

        $response = $this->doPostRequest($requestUrl, $fields, $store);
        
        if (!$response || $response->getStatus() != 200){
            return false;
        }
        
        return json_decode($response->getBody());
    }    
    

     /* Send quote and order history
     *
     * @param int $quoteId
     * @param string|int $store
     * @return Mage_Sales_Model_Quote
     */
    public function sendHistory()
    {
        $store = $this->getStoreFromAdmin();
        
        $lastOrder = $this->sendLastOrders($store);
        if ($lastOrder){
            $this->sendLastQuotes($store, $lastOrder->getCreatedAt());
        }
    }
    
    private function sendLastOrders($store){
        $orders = [];
        $last = null;
        $items = Mage::getModel('sales/order')
                    ->getCollection()
                    ->setOrder('created_at', 'desc')       
                    ->setPageSize($this->historySize)
                    ->join(array('stores' => 'core/store'), 'main_table.store_id=stores.store_id')
                    ->addFieldToFilter('stores.website_id', $store->getWebsiteId())
                    ->addFieldToFilter('created_at', array('gt' => date("Y-m-d H:i:s", strtotime('-1 month'))));

        //Check count of items
        if (count($items) > $this->historySize){
                $items = Mage::getModel('sales/order')
                    ->getCollection()
                    ->setOrder('created_at', 'desc')
                    ->join(array('stores' => 'core/store'), 'main_table.store_id=stores.store_id')
                    ->addFieldToFilter('stores.website_id', $store->getWebsiteId())
                    ->addFieldToFilter('created_at', array('gt' => date("Y-m-d H:i:s", strtotime('-1 month'))))
                    ->setPageSize($this->historySize);
        }        
        else {
                $items = Mage::getModel('sales/order')
                    ->getCollection()
                    ->setOrder('created_at', 'desc')
                    ->join(array('stores' => 'core/store'), 'main_table.store_id=stores.store_id')
                    ->addFieldToFilter('stores.website_id', $store->getWebsiteId())
                    ->addFieldToFilter('created_at', array('gt' => date("Y-m-d H:i:s", strtotime('-12 month'))))
                    ->setPageSize($this->historySize);
        }
        
        foreach($items as $item){
            $order = Mage::getModel("sales/order")->load($item->getId()); 
            $last = $order;
            
            //Get order data
            $orderData = $this->getOrderData($order, $store);

            //Append only if we get it
            if (!empty($orderData)){
                $orders[] = $orderData;
            }
        }

        //Push orders to api
        if (!empty($orders)){
            $this->doPostRequest('/import/orders', $orders, $store);    
        }
        
        return $last;
    }
    
    private function sendLastQuotes($store, $since){
        $quotes = [];
        $last = null;
        $items = Mage::getModel('sales/quote')
                ->getCollection()
                ->setOrder('created_at', 'asc')
                ->join(array('stores' => 'core/store'), 'main_table.store_id=stores.store_id')
                ->addFieldToFilter('stores.website_id', $store->getWebsiteId())
                ->addFieldToFilter('created_at', array('gt' =>  $since));

        foreach($items as $item){
            $quote = Mage::getModel("sales/quote")->loadByIdWithoutStore($item->getId());
            $last = $quote;
            
            if ($quote){
                //Get quote data
                $quoteData = $this->getAbadonnedCartData($quote, $store);        

                //Append only if we get it
                if ($quoteData){
                    $quotes[] = $quoteData;
                }
            }
        }

        //Push quotes to api
        if (!empty($quotes)){
            $this->doPostRequest('/import/carts', $quotes, $store);    
        }
        
        return $last;
    }
    
    
    /**
     * This method send data on api path
     * @param $apiPath
     * @param $fields
     * @return Zend_Http_Response
     */
    private function doPostRequest($apiPath, $fields, $store=null)
    {
        try {
            $url = $this->apiBaseUrl . $apiPath;
            $client = new Zend_Http_Client($url);
            $client->setHeaders('x-auth-key', $this->getStoreConfig('auth', $store));
            $client->setUri($url);
            $client->setRawData(json_encode($fields), 'application/json');
            $response = $client->request(Zend_Http_Client::POST);
        } catch (Exception $e) {}

        return $response;
    }

}