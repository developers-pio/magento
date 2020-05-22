<?php
namespace PIO\Customshipping\Model;

use Magento\Backend\App\Area\FrontNameResolver;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\State;
use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory;
use Magento\Quote\Model\Quote\Address\RateResult\MethodFactory;
use Magento\Shipping\Helper\Carrier as ShippingCarrierHelper;
use Magento\Shipping\Model\Carrier\AbstractCarrier;
use Magento\Shipping\Model\Carrier\CarrierInterface;
use Magento\Shipping\Model\Rate\ResultFactory;
use PIO\Customshipping\Helper\Data;
use Psr\Log\LoggerInterface;

class Carrier extends AbstractCarrier implements CarrierInterface
{
    const CODE = 'customshipping';

    protected $_code = self::CODE;
    protected $_rateMethodFactory;
    protected $_carrierHelper;
    protected $_rateFactory;
    protected $_state;
    protected $_CustomshippingHelper;

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        ErrorFactory $rateErrorFactory,
        LoggerInterface $logger,
        ResultFactory $rateFactory,
        ShippingCarrierHelper $carrierHelper,
        MethodFactory $rateMethodFactory,
        State $state,
        Data $CustomshippingHelper,
        array $data = []
    ) {
        parent::__construct($scopeConfig, $rateErrorFactory, $logger, $data);
        $this->_scopeConfig = $scopeConfig;
        $this->_rateErrorFactory = $rateErrorFactory;
        $this->_logger = $logger;
        $this->_rateFactory = $rateFactory;
        $this->_carrierHelper = $carrierHelper;
        $this->_rateMethodFactory = $rateMethodFactory;
        $this->_state = $state;
        $this->_CustomshippingHelper = $CustomshippingHelper;
    }

    public function collectRates(RateRequest $request)
    {
        $result = $this->_rateFactory->create();

        if (!$this->getConfigFlag('active')) {
            return $result;
        }

        $price = 0;
        if ($request->getAllItems()) {
            foreach ($request->getAllItems() as $item) {
                if ($item->getProduct()->isVirtual() || $item->getParentItem()) {
                    continue;
                }

                if ($item->getHasChildren() && $item->isShipSeparately()) {
                    foreach ($item->getChildren() as $child) {
                        if ($child->getFreeShipping() && !$child->getProduct()->isVirtual()) {
                            $ship_price = $child->getQty() * $child->getPrice();
                            $price += (float)$ship_price;
                        }
                    }
                } else{
                    $ship_price = $item->getQty() * $item->getPrice();
                    $price += (float)$ship_price;
                }
            }
        }

        foreach ($this->_CustomshippingHelper->getShippingType() as $shippingType) {
            //admin methods
            $admin_methods = $this->get_custom_methods('admin');
            //skip admin method on frontend
            if(!$this->isAdmin() && (in_array($shippingType['code'], $admin_methods) || strpos($shippingType['code'], 'admin') !== false)){
                continue;
            }

            //change shipping method title for admin
            $shipping_title = $shippingType['title'];
            if($this->isAdmin() && $shippingType['code'] == 'ground'){
                $shipping_title = '*Standard*';
            }

            $rate = $this->_rateMethodFactory->create();
            $rate->setCarrier($this->_code);
            $rate->setCarrierTitle($this->getConfigData('title'));
            $rate->setMethod($shippingType['code']);
            $rate->setMethodTitle($shipping_title);

            $custom_methods = $this->get_custom_methods();
            if(in_array($shippingType['code'], $custom_methods)){
                //custom shipping price logic
                $real_ship_price = $this->getShippingprice($request,$shippingType['code'],$shippingType['price'],$price);
            }else{
                $real_ship_price = $shippingType['price'];
            }

            $rate->setCost($real_ship_price);
            $rate->setPrice($real_ship_price);

            $result->append($rate);

        }

        return $result;
    }

    public function getAllowedMethods()
    {
        return [$this->getCarrierCode() => __($this->getConfigData('name'))];
    }

    public function isTrackingAvailable()
    {
        return false;
    }

    protected function isAdmin()
    {
        return $this->_state->getAreaCode() == FrontNameResolver::AREA_CODE;
    }

    //custom shipping price logic
    public function getShippingprice($request, $ship_code, $ship_price, $price){
        
        $real_ship_price = $ship_price;

        //different shipping methods and its rates, percentage
        $basic = array('ground_f' => array([0],[0,0],[0,0]));

        $new_basic = array(
                    'ground' => array([0.12],[4,.7],[23,.12]),
                    'ground_1' => array([0.01],[4,.7],[23,.12]),
                    'ground_2' => array([0.02],[4,.7],[23,.12]),
                    'ground_3' => array([0.03],[4,.7],[23,.12]),
                    );

        $surcharge = array(
                    'secondday' => array([0.5],[2,.2],[5,.5]),
                    'nextday' => array([0.5],[2,.2],[5,.5])
                    );

        $shippingrates = array('basic' => $basic, 'new_basic' => $new_basic, 'surcharge' => $surcharge);

        $ship_type = $this->checkshiptype($shippingrates,$ship_code);

        $perc = $shippingrates[$ship_type][$ship_code][0][0];

        
        if($ship_type == 'new_basic'){

            //new basic
            //shipping percentage
            $subtotal = $request->getBaseSubtotalInclTax();
            if($subtotal < 1000){
                $real_ship_price = ($price*$perc)+$ship_price;
            }else{
                $real_ship_price = $price*$perc;
            }

        }elseif ($ship_type == 'surcharge') {

            //surcharge
            $real_ship_price = ($price*$perc)+$ship_price;

        }else{

            //basic
            $real_ship_price = $ship_price;
            if($price*$perc > $ship_price){
                $real_ship_price = $price*$perc;
            }

        }

        return $real_ship_price;
    }

    public function checkshiptype($shippingrates,$ship_code){
        $type = 'basic';
        if(in_array($ship_code, array_keys($shippingrates['new_basic']))){
            $type = 'new_basic';
        }elseif (in_array($ship_code, array_keys($shippingrates['surcharge']))) {
            $type = 'surcharge';
        }
        return $type;
    }

    public function get_custom_methods($type=''){
        //all custom methods
        $methods = array('ground','ground_f','ground_1','ground_2','secondday','nextday');

        if($type == 'admin'){
            //admin sepecific methods
            $methods = array('ground_f','ground_1','ground_2');

        }
        return $methods;
    }



}