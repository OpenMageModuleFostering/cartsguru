<?php
/* @var $installer Mage_Core_Model_Resource_Setup */
$installer = $this;
$magentoVersion = Mage::getVersion();

$installer->startSetup();

$connection = $installer->getConnection();
$connection->addColumn($this->getTable('sales/quote'), 'cartsguru_token', 'varchar(255) NOT NULL');

$installer->endSetup();
