<?php

class Cartsguru_Block_Pixel extends Mage_Core_Block_Template
{
    /**
     * Check if Facebook is enabled
     */
    protected function isFacebookEnabled()
    {
        return Mage::helper('cartsguru')->getStoreConfig("feature_facebook");
    }

    /**
     * Get FB pixel from config
     */
    protected function getPixel()
    {
        return Mage::helper('cartsguru')->getStoreConfig("facebook_pixel");
    }

    /**
     * Get CatalogId from config
     */
    protected function getCatalogId()
    {
        return Mage::helper('cartsguru')->getStoreConfig("facebook_catalogId");
    }

    /**
     * Get the product added to cart that we saved in session
     */
    protected function getAddToCartProduct()
    {
        $productData = Mage::getSingleton('core/session')->getCartsGuruAddToCart();
        if ($productData) {
            Mage::getSingleton('core/session')->unsCartsGuruAddToCart();
            return $productData;
        }
        return false;
    }
    /**
     * Get the tracking URL
     */
    protected function getTrackingURL()
    {
        return $this->getUrl('cartsguru/saveaccount');
    }
}
