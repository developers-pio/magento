<?php
namespace PIO\Customshipping\Block\Adminhtml\Form\Field;

use Magento\Config\Block\System\Config\Form\Field\FieldArray\AbstractFieldArray;

class Shippingmethods extends AbstractFieldArray
{
    protected $customshippinghelper;

    public function __construct(\Magento\Backend\Block\Template\Context $context,
                                \PIO\Customshipping\Helper\Data $customshippinghelper,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->customshippinghelper = $customshippinghelper;
    }

    public function _prepareToRender()
    {
        foreach ($this->customshippinghelper->getCustomShippingTitles() as $key => $shippingcolumn) {
            $this->addColumn($key,
                [
                    'label' => __($shippingcolumn['label']),
                    'class' => $shippingcolumn['class']
                ]
            );
        }
        $this->_addAfter = false;
        $this->_addButtonLabel = __('Add');
    }
}