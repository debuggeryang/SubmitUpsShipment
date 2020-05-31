<?php
/**
 *
 */
namespace BiFang\SubmitUpsShipment\Observer;

use Dhl\Shipping\Model\Config\ModuleConfigInterface;
use Dhl\Shipping\Model\Shipping\Carrier;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

/**
 */
class ChangePackagingTemplateObserver extends \Dhl\Shipping\Observer\ChangePackagingTemplateObserver
{
    /**
     * @var \Magento\Framework\Registry
     */
    private $coreRegistry;

    /**
     * @var ModuleConfigInterface
     */
    private $moduleConfig;

    /**
     * ChangePackagingTemplateObserver constructor.
     * @param \Magento\Framework\Registry $registry
     * @param ModuleConfigInterface $moduleConfig
     */
    public function __construct(
        \Magento\Framework\Registry $registry,
        ModuleConfigInterface $moduleConfig
    ) {
        $this->coreRegistry = $registry;
        $this->moduleConfig = $moduleConfig;

    }

    /**
     * @param Observer $observer
     */
    public function execute(Observer $observer)
    {
        $block = $observer->getEvent()->getBlock();
        if ($block instanceof \Magento\Shipping\Block\Adminhtml\Order\Packaging
            && $block->getNameInLayout() === 'shipment_packaging'
        ) {
            /** @var \Magento\Sales\Model\Order\Shipment $currentShipment */
            $currentShipment = $this->coreRegistry->registry('current_shipment');
            /** @var \Magento\Sales\Api\Data\OrderInterface|\Magento\Sales\Model\Order $order */
            $order = $currentShipment->getOrder();
            $shippingMethod = $order->getShippingMethod(true);
            // Since tablerate initially is selected but ups/dhl is applied in the end, we disable the carrier_code check here
            // if ($shippingMethod->getData('carrier_code') === Carrier::CODE) {
                $block->setTemplate('BiFang_SubmitUpsShipment::order/packaging/popup.phtml');
            // }
        }
    }
}
