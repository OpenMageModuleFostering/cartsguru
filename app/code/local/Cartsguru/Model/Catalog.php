<?php

/**
* This class generates catalog feed for Facebook
* Class Cartsguru_Model_Catalog
*/
class Cartsguru_Model_Catalog {

    /**
    * @var XMLWriter
    */
    protected $doc;

    /**
    * The fields to be put into the feed.
    * @var array
    */
    protected $_requiredFields = array(
        array(
            'magento'   => 'id',
            'feed'      => 'g:id',
            'type'      => 'id',
        ),
        array(
            'magento'   => 'availability_google',
            'feed'      => 'g:availability',
            'type'      => 'computed',
        ),
        // condition here
        array(
            'magento'   => 'description',
            'feed'      => 'g:description',
            'type'      => 'product_attribute',
        ),
        array(
            'magento'   => 'image_url',
            'feed'      => 'g:image_link',
            'type'      => 'computed',
        ),
        array(
            'magento'   => 'product_link',
            'feed'      => 'g:link',
            'type'      => 'computed',
        ),
        array(
            'magento'   => 'name',
            'feed'      => 'g:title',
            'type'      => 'product_attribute',
        ),
        array(
            'magento'   => 'manufacturer',
            'feed'      => 'g:brand',
            'type'      => 'product_attribute',
        ),
        array(
            'magento'   => 'price',
            'feed'      => 'g:price',
            'type'      => 'computed',
        )
    );

    /*
    * Generate XML product feed
    */
    public function generateFeed()
    {
        // setup attribute mapping
        $this->_attributes = array();
        foreach ($this->_requiredFields as $requiredField) {
            $this->_attributes[$requiredField['feed']] = $requiredField;
        }

        $this->setupHeader(Mage::app()->getStore());
        $productCollection = Mage::getResourceModel('catalog/product_collection');
        $productCollection->addStoreFilter();

        $this->_products = array();
        Mage::getSingleton('core/resource_iterator')->walk($productCollection->getSelect(), array(array($this, 'processProduct')));

        $this->setupFooter();

        return $this->doc->flush();
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

        $price = floatval($product_data['g:price']);
        // Price is required
        if (empty($price)) {
            return;
        }

        // If manufacturer not set use mpn === sku
        if ($product_data['g:brand'] === '') {
            unset($product_data['g:brand']);
            $product_data['g:mpn'] = $product_data['g:id'];
        }

        // All products are new
        $product_data['g:condition'] = 'new';

        // Sart new feed entry
        $this->doc->startElement('entry');

        foreach ($product_data as $feedTag => $value) {
            $safeString = null;
            switch ($feedTag) {
                case 'g:link':
                $safeString = $value;
                break;

                case 'g:price':
                $safeString = sprintf('%.2f', $store->convertPrice($value, false, false)).' '.Mage::getStoreConfig('currency/options/default', $store->getStoreId());
                break;

                case 'g:sale_price':
                if($value && $value != ''){
                    $safeString = sprintf('%.2f', $store->convertPrice($value, false, false)).' '.Mage::getStoreConfig('currency/options/default', $store->getStoreId());
                }
                break;

                case 'g:image_link':
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
                $this->doc->writeElement($feedTag, $safeString);
            }
        }
        $this->doc->endElement();
    }

    /*
    * Instantiate the XML object
    */
    protected function setupHeader($store)
    {
        $this->doc = new XMLWriter();
        $this->doc->openMemory();
        $this->doc->setIndent(true);
        $this->doc->setIndentString('    ');
        $this->doc->startDocument('1.0', 'UTF-8');
        $this->doc->startElement('feed');
        $this->doc->writeAttribute('xmlns', 'http://www.w3.org/2005/Atom');
        $this->doc->writeAttribute('xmlns:g', 'http://base.google.com/ns/1.0');
        $this->doc->writeElement('title', $store->getName());
        $this->doc->startElement('link');
        $this->doc->writeAttribute('rel', 'self');
        $this->doc->writeAttribute('href', $store->getBaseUrl());
        $this->doc->endElement();
    }

    /*
    * Close the XML object
    */
    protected function setupFooter()
    {
        $this->doc->endElement();
        $this->doc->endDocument();
    }
}
