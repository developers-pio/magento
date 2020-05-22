<?php
namespace PIO\Customshipping\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Store\Model\ScopeInterface;
use PIO\Customshipping\Model\Carrier;

class Data extends AbstractHelper
{
    protected $customshippingtype;
    protected $headerTemplate;
    protected $serialize;

    public function __construct(Context $context,\Magento\Framework\Serialize\Serializer\Json $serialize)
    {
        $this->serialize = $serialize;
        parent::__construct($context);
    }

    public function IsCustomShippingEnable()
    {
        return $this->scopeConfig->isSetFlag('carriers/' . Carrier::CODE . '/active');
    }

    public function getConfigData($field)
    {
        return $this->scopeConfig->getValue('carriers/'.Carrier::CODE.'/'.$field,ScopeInterface::SCOPE_STORE);
    }

    public function getCustomShippingTitles()
    {
        return [
            'title' => [
                'label' => 'Title',
                'class' => 'validate-no-empty',
                'default' => ''
            ],
            'code' => [
                'label' => 'Code',
                'class' => 'validate-no-empty validate-data',
                'default' => ''
            ],
            'price' => [
                'label' => 'Price',
                'class' => 'validate-no-empty greater-than-equals-to-0',
                'default' => ''
            ],
            'sort_order' => [
                'label' => 'Sort Order',
                'class' => 'validate-no-empty greater-than-equals-to-0',
                'default' => 99
            ]
        ];
    }

    public function getCustomShippingList()
    {
        if (!$this->headerTemplate) {
            $this->headerTemplate = [];

            foreach ($this->getCustomShippingTitles() as $key => $column) {
                $this->headerTemplate[$key] = $column['default'];
            }
        }

        return $this->headerTemplate;
    }

    public function CustomshippingArray($values)
    {
        $requiredFields = $this->getCustomShippingList();

        if (is_array($values)) {
            foreach ($values as &$row) {
                $row = array_merge($requiredFields, $row);
            }
        }
        return $values;
    }

    public function IsCustomShippingJson($string)
    {
        json_decode($string);
        return (json_last_error() == JSON_ERROR_NONE);
    }

    public function getShippingType()
    {
        if (!$this->customshippingtype) {
            $customshippingarray = [];
            $customshippingtype = $this->getConfigData('shipping_type');

            if (is_string($customshippingtype) && !empty($customshippingtype) && $customshippingtype !== '[]') {
                if ($this->IsCustomShippingJson($customshippingtype)) {
                    $customshippingarray = (array) json_decode($customshippingtype, true);
                } else {
                    $customshippingarray = (array) array_values($this->serialize->unserialize($customshippingtype));
                }
            }

            $customshippingarray = $this->CustomshippingArray($customshippingarray);

            usort($customshippingarray, function ($a, $b) {
                if (array_key_exists('sort_order', $a)) {
                    return $a['sort_order'] - $b['sort_order'];
                } else {
                    return 0;
                }
            });

            $this->customshippingtype = $customshippingarray;
        }
        return $this->customshippingtype;
    }
}