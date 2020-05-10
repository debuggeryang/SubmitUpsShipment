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
        $this->zend_logger = new \Zend\Log\Logger();
        $this->zend_logger->addWriter(new \Zend\Log\Writer\Stream(BP . '/var/log/ups.log'));

    }

    /**
     * @param Observer $observer
     */
    public function execute(Observer $observer)
    {
      $this->zend_logger->info("observer overwritten");
        $block = $observer->getEvent()->getBlock();
        if ($block instanceof \Magento\Shipping\Block\Adminhtml\Order\Packaging
            && $block->getNameInLayout() === 'shipment_packaging'
        ) {
            /** @var \Magento\Sales\Model\Order\Shipment $currentShipment */
            $currentShipment = $this->coreRegistry->registry('current_shipment');
            /** @var \Magento\Sales\Api\Data\OrderInterface|\Magento\Sales\Model\Order $order */
            $order = $currentShipment->getOrder();
            $shippingMethod = $order->getShippingMethod(true);
            if ($shippingMethod->getData('carrier_code') === Carrier::CODE) {
                $block->setTemplate('BiFang_SubmitUpsShipment::order/packaging/popup.phtml');
            }
        }
    }
}
