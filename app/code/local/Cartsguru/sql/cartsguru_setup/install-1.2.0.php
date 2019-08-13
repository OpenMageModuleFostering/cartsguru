<?php
/* @var $installer Mage_Core_Model_Resource_Setup */
$installer = $this;

$installer->startSetup();
$connection = $installer->getConnection();

$connection->addColumn($this->getTable('sales/quote'), 'cartsguru_token', 'varchar(255) NOT NULL');

$setup = Mage::getModel('customer/entity_setup', 'core_setup');

$installer_core= new Mage_Sales_Model_Resource_Setup('core_setup');

$options = array(
    'type'     => Varien_Db_Ddl_Table::TYPE_VARCHAR,
    'visible'  => false,
    'required' => false
);
foreach ($entities as $entity) {
    $installer_core->addAttribute('quote', 'cartsguru_token', $options);
}
$setup->endSetup();
$installer_core->endSetup();
$installer->endSetup();