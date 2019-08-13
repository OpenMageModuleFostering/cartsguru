<?php

class Cartsguru_CatalogController extends Mage_Core_Controller_Front_Action {

    public function indexAction(){
        $catalog = Mage::getModel('cartsguru/catalog');
        header('Content-Type: application/xml; charset=utf-8');
        echo $catalog->generateFeed();
    }
}
