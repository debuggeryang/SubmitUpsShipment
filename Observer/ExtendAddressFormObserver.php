<?php

namespace BiFang\SubmitUpsShipment\Observer;

use Dhl\Shipping\Api\OrderAddressExtensionRepositoryInterface;
use Dhl\Shipping\Block\Adminhtml\Order\Shipping\Address\Form;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Registry;
use Magento\Sales\Block\Adminhtml\Order\Address;


class ExtendAddressFormObserver extends \Dhl\Shipping\Observer\ExtendAddressFormObserver
{
    /**
     * @var Registry
     */
    private $coreRegistry;

    /**
     * @var OrderAddressExtensionRepositoryInterface
     */
    private $addressExtensionRepository;

    /**
     * ExtendAddressFormObserver constructor.
     *
     * @param Registry $coreRegistry
     * @param OrderAddressExtensionRepositoryInterface $addressExtensionRepository
     */
    public function __construct(
        Registry $coreRegistry,
        OrderAddressExtensionRepositoryInterface $addressExtensionRepository
    ) {
        $this->coreRegistry = $coreRegistry;
        $this->addressExtensionRepository = $addressExtensionRepository;
    }

    /**
     * When the shipping address edit page in the backend is loaded, add the shipping address data into the form
     *
     * Event:
     * - adminhtml_block_html_before
     *
     * @param Observer $observer
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function execute(Observer $observer)
    {
        /** @var Address $container */
        $container = $observer->getEvent()->getData('block');
        if (!$container instanceof Address) {
            return;
        }

        /** @var \Magento\Sales\Model\Order\Address $address */
        $address = $this->coreRegistry->registry('order_address');
        if (!$address || ($address->getAddressType() !== \Magento\Sales\Model\Order\Address::TYPE_SHIPPING)) {
            return;
        }

        $shippingMethod = $address->getOrder()->getShippingMethod(true);

        // Since tablerate initially is selected but ups/dhl is applied in the end, we disable the carrier_code check here
        // if ($shippingMethod->getData('carrier_code') !== \Dhl\Shipping\Model\Shipping\Carrier::CODE) {
        //     return;
        // }

        try {
            // load previous info data
            $this->addressExtensionRepository->getShippingInfo($address->getEntityId());
        } catch (NoSuchEntityException $e) {
            return;
        }

        $origAddressForm = $container->getChildBlock('form');
        if (!$origAddressForm instanceof \Magento\Sales\Block\Adminhtml\Order\Create\Form\Address) {
            return;
        }

        /** @var Form $dhlAddressForm */
        $dhlAddressForm = $container->getLayout()->getBlock('shipping_sales_order_address_form');
        $dhlAddressForm->setDisplayVatValidationButton($origAddressForm->getDisplayVatValidationButton());
        $container->setChild('form', $dhlAddressForm);
    }
}
