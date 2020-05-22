<?php
namespace PIO\Customshipping\Model\Config\Backend;

use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Value as ConfigValue;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;

class Shippingmethods extends ConfigValue
{
    protected $serializer;

    public function __construct(
        Context $context,
        Registry $registry,
        ScopeConfigInterface $config,
        TypeListInterface $cacheTypeList,
        AbstractResource $resource = null,
        AbstractDb $resourceCollection = null,
        \Magento\Framework\Serialize\Serializer\Json $serializer,
        array $data = []
    ) {
        $this->serializer = $serializer;
        parent::__construct($context, $registry, $config, $cacheTypeList, $resource, $resourceCollection, $data);
    }

    public function beforeSave()
    {
        $value = $this->getValue();
        unset($value['__empty']);
        $encodedValue = $this->serializer->serialize($value);
        $this->setValue($encodedValue);
    }

    protected function _afterLoad()
    {
        $value = $this->getValue();
        if($value != '' || $value != null)
        {
            $decodedValue = $this->serializer->unserialize($value);
            $this->setValue($decodedValue);
        }
    }
}