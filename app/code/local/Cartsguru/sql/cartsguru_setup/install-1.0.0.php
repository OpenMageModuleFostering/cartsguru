<?php
/* @var $installer Mage_Core_Model_Resource_Setup */
$installer = $this;

$installer->startSetup();
$connection = $installer->getConnection();

$connection->addColumn($this->getTable('sales_flat_quote'), 'telephone', 'varchar(255) NOT NULL');
$connection->addColumn($this->getTable('sales_flat_order'), 'telephone', 'varchar(255) NOT NULL');

$connection->addColumn($this->getTable('sales_flat_quote'), 'country', 'varchar(255) NOT NULL');
$connection->addColumn($this->getTable('sales_flat_order'), 'country', 'varchar(255) NOT NULL');

$setup = Mage::getModel('customer/entity_setup', 'core_setup');

$installer_core= new Mage_Sales_Model_Resource_Setup('core_setup');
$installer_core->addAttribute('catalog_product', 'telephone', array(
    'group'             => 'General',
    'type'              => Varien_Db_Ddl_Table::TYPE_VARCHAR,
    'backend'           => '',
    'frontend'          => '',
    'label'             => 'Telephone',
    'input'             => 'text',
    'class'             => '',
    'source'            => '',
    'global'            => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_STORE,
    'visible'           => true,
    'required'          => false,
    'user_defined'      => true,
    'default'           => '',
    'searchable'        => true,
    'filterable'        => true,
    'comparable'        => true,
    'visible_on_front'  => true,
    'unique'            => false,
    'apply_to'          => 'simple,configurable,virtual',
    'is_configurable'   => false
));

/**
 * Add 'custom_attribute' attribute for entities
 */
$entities = array(
    'quote',
    'quote_address',
    'quote_item',
    'quote_address_item',
    'order',
    'order_item'
);
$options = array(
    'type'     => Varien_Db_Ddl_Table::TYPE_VARCHAR,
    'visible'  => true,
    'required' => false
);
foreach ($entities as $entity) {
    $installer_core->addAttribute($entity, 'telephone', $options);
}
$setup->endSetup();
$installer_core->endSetup();
$installer->endSetup();