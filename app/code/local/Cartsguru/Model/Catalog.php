<?php

/**
* This class generates catalog feed for Facebook
* Class Cartsguru_Model_Catalog
*/
class Cartsguru_Model_Catalog {

    /**
    * The fields to be put into the feed.
    * @var array
    */
    protected $_requiredFields = array(
        array(
            'magento'   => 'id',
            'feed'      => 'id',
            'type'      => 'id',
        ),
        array(
            'magento'   => 'availability_google',
            'feed'      => 'availability',
            'type'      => 'computed',
        ),
        // condition here
        array(
            'magento'   => 'description',
            'feed'      => 'description',
            'type'      => 'product_attribute',
        ),
        array(
            'magento'   => 'image_url',
            'feed'      => 'image_link',
            'type'      => 'computed',
        ),
        array(
            'magento'   => 'product_link',
            'feed'      => 'link',
            'type'      => 'computed',
        ),
        array(
            'magento'   => 'name',
            'feed'      => 'title',
            'type'      => 'product_attribute',
        ),
        array(
            'magento'   => 'manufacturer',
            'feed'      => 'brand',
            'type'      => 'product_attribute',
        ),
        array(
            'magento'   => 'price',
            'feed'      => 'price',
            'type'      => 'computed',
        )
    );

    /*
    * Generate XML product feed
    */
    public function generateFeed($store, $offset, $limit)
    {
        // setup attribute mapping
        $this->_attributes = array();

        foreach ($this->_requiredFields as $requiredField) {
            $this->_attributes[$requiredField['feed']] = $requiredField;
        }

        $result = array(
            'url' => $store->getBaseUrl(),
            'store_name' => $store->getFrontendName(),
            'total' => Mage::getModel('catalog/product')->getCollection()->addStoreFilter()->addFieldToFilter('status', '1')->getSize()
        );

        $productCollection = Mage::getResourceModel('catalog/product_collection');
        $productCollection->addStoreFilter();
        $productCollection->addFieldToFilter('status', '1');

        $this->_products = array();
        Mage::getSingleton('core/resource_iterator')->walk($productCollection->getSelect()->limit($limit, $offset), array(array($this, 'processProduct')));
        $result['products'] = $this->_products;

        return $result;
    }

    /*
    * Process each product in a loop
    */
    public function processProduct($args)
    {
        $product = Mage::getModel('catalog/product')->load($args['row']['entity_id']);

        $product_data = array();
        $attributes = $this->_attributes;
        // store
        $store = Mage::getModel('core/store')->load($product->getStoreId());
        // Prepare attributes
        foreach ($attributes as $attribute) {
            if ($attribute['type'] == 'id') {
                $value = $product->getId();
            } elseif ($attribute['type'] == 'product_attribute') {
                // if this is a normal product attribute, retrieve it's frontend representation
                if ($product->getData($attribute['magento']) === null) {
                    $value = '';
                } else {
                    /** @var $attributeObj Mage_Catalog_Model_Resource_Eav_Attribute */
                    $attributeObj = $product->getResource()->getAttribute($attribute['magento']);
                    $value = $attributeObj->getFrontend()->getValue($product);
                }
            } elseif ($attribute['type'] == 'computed') {
                // if this is a computed attribute, handle it depending on its code
                switch ($attribute['magento']) {
                    case 'price':
                    $price = Mage::helper('tax')->getPrice($product, $product->getFinalPrice(), true);
                    $value = sprintf('%.2f', (float)($store->convertPrice($price, false, false)));
                    $value .= ' '.Mage::getStoreConfig('currency/options/default', $product->getStoreId());
                    break;

                    case 'product_link':
                    $value = $product->getProductUrl();
                    break;

                    case 'image_url':
                    $value = (string)Mage::helper('catalog/image')->init($product, 'image');
                    break;

                    case 'availability_google':
                    $value = $product->isSaleable() ? 'in stock' : 'out of stock';
                    break;

                    default:
                    $value = '';
                }
            }
            $product_data[$attribute['feed']] = $value;
        }

        $price = floatval($product_data['price']);
        // Price is required
        if (empty($price)) {
            return;
        }

        // If manufacturer not set use mpn === sku
        if ($product_data['brand'] === '') {
            unset($product_data['brand']);
            $product_data['mpn'] = $product_data['id'];
        }

        // All products are new
        $product_data['condition'] = 'new';

        foreach ($product_data as $feedTag => $value) {
            $safeString = null;
            switch ($feedTag) {
                case 'link':
                $safeString = $value;
                break;

                case 'price':
                $safeString = sprintf('%.2f', $store->convertPrice($value, false, false)).' '.Mage::getStoreConfig('currency/options/default', $store->getStoreId());
                break;

                case 'sale_price':
                if($value && $value != ''){
                    $safeString = sprintf('%.2f', $store->convertPrice($value, false, false)).' '.Mage::getStoreConfig('currency/options/default', $store->getStoreId());
                }
                break;

                case 'image_link':
                if ($value == 'no_selection') {
                    $safeString = '';
                } else {
                    $safeString = $value;
                    // Check if the link is a full URL
                    if (substr($value, 0, 5) != 'http:' && substr($value, 0, 6) != 'https:') {
                        $safeString = $store->getBaseUrl('media') . 'catalog/product' . $value;
                    }
                }
                break;

                default:
                $safeString = $value;
                break;
            }
            if ($safeString !== null) {
                $product_data[$feedTag] = $safeString;
            }
        }
        $this->_products[] = $product_data;
    }
}
