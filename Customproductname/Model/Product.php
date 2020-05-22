<?php

namespace PIO\Customproductname\Model;

class Product extends \Magento\Catalog\Model\Product
{
    public function getName()
    {

        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $state =  $objectManager->get('Magento\Framework\App\State');
        $product = $objectManager->create('Magento\Catalog\Model\Product')->load($this->_getData('entity_id'));
        $name_prefix = $product->getResource()->getAttribute('name_prefix')->getFrontend()->getValue($product);
        $changeNamebyPreference = $this->_getData('name');
            if($state->getAreaCode() == 'frontend'):
                $changeNamebyPreference = $name_prefix !='' ? $name_prefix.' '. $this->_getData('name') : $this->_getData('name');
            endif;
        return $changeNamebyPreference;
    }
}