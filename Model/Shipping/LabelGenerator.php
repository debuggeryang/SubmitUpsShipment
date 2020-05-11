<?php
/**
 *
 * Copyright © 2013-2017 Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace BiFang\SubmitUpsShipment\Model\Shipping;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\RequestInterface;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class LabelGenerator extends \Magento\Shipping\Model\Shipping\LabelGenerator
{
    /**
     * @var \Magento\Shipping\Model\CarrierFactory
     */
    protected $carrierFactory;

    /**
     * @var \Magento\Shipping\Model\Shipping\LabelsFactory
     */
    protected $labelFactory;

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var \Magento\Sales\Model\Order\Shipment\TrackFactory
     */
    protected $trackFactory;

    /**
     * @var \Magento\Framework\Filesystem
     */
    protected $filesystem;

    /**
     * @param \Magento\Shipping\Model\CarrierFactory $carrierFactory
     * @param LabelsFactory $labelFactory
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Sales\Model\Order\Shipment\TrackFactory $trackFactory
     * @param \Magento\Framework\Filesystem $filesystem
     */
    public function __construct(
        \Magento\Shipping\Model\CarrierFactory $carrierFactory,
        \Magento\Shipping\Model\Shipping\LabelsFactory $labelFactory,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Sales\Model\Order\Shipment\TrackFactory $trackFactory,
        \Magento\Framework\Filesystem $filesystem
    ) {
        $this->carrierFactory = $carrierFactory;
        $this->labelFactory = $labelFactory;
        $this->scopeConfig = $scopeConfig;
        $this->trackFactory = $trackFactory;
        $this->filesystem = $filesystem;
        $this->zend_logger = new \Zend\Log\Logger();
        $this->zend_logger->addWriter(new \Zend\Log\Writer\Stream(BP . '/var/log/ups.log'));

    }

    /**
     * @param \Magento\Sales\Model\Order\Shipment $shipment
     * @param RequestInterface $request
     * @return void
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function create(\Magento\Sales\Model\Order\Shipment $shipment, RequestInterface $request)
    {
        $order = $shipment->getOrder();
        $shippingCarrierCode = $request->getParam("shipment")["create_shipping_label"];
        $carrier = $this->carrierFactory->create($shippingCarrierCode);

        $shippingMethodCode = explode(",",$carrier->getConfigData('allowed_methods'))[0];

        $order->setShippingMethod($shippingCarrierCode . "_" . $shippingMethodCode);

        if (!$carrier->isShippingLabelsAvailable()) {
            throw new \Magento\Framework\Exception\LocalizedException(__('Shipping labels is not available.'));
        }
        $shipment->setPackages($request->getParam('packages'));
        $response = $this->labelFactory->create()->requestToShipment($shipment);
        if ($response->hasErrors()) {
          $this->zend_logger->info("errors when creating label");
            throw new \Magento\Framework\Exception\LocalizedException(__($response->getErrors()));
        }
        if (!$response->hasInfo()) {
            throw new \Magento\Framework\Exception\LocalizedException(__('Response info is not exist.'));
        }
        $this->zend_logger->info("label created");
        $labelsContent = [];
        $trackingNumbers = [];
        $info = $response->getInfo();
        foreach ($info as $inf) {
            if (!empty($inf['tracking_number']) && !empty($inf['label_content'])) {
                $labelsContent[] = $inf['label_content'];
                $trackingNumbers[] = $inf['tracking_number'];
            }
        }
        $outputPdf = $this->combineLabelsPdf($labelsContent);
        $shipment->setShippingLabel($outputPdf->render());
        $carrierCode = $carrier->getCarrierCode();
        $carrierTitle = $this->scopeConfig->getValue(
            'carriers/' . $carrierCode . '/title',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $shipment->getStoreId()
        );
        if (!empty($trackingNumbers)) {
            $this->addTrackingNumbersToShipment($shipment, $trackingNumbers, $carrierCode, $carrierTitle);
        }
    }

    /**
     * @param \Magento\Sales\Model\Order\Shipment $shipment
     * @param array $trackingNumbers
     * @param string $carrierCode
     * @param string $carrierTitle
     *
     * @return void
     */
    private function addTrackingNumbersToShipment(
        \Magento\Sales\Model\Order\Shipment $shipment,
        $trackingNumbers,
        $carrierCode,
        $carrierTitle
    ) {
        foreach ($trackingNumbers as $number) {
            if (is_array($number)) {
                $this->addTrackingNumbersToShipment($shipment, $number, $carrierCode, $carrierTitle);
            } else {
                $shipment->addTrack(
                    $this->trackFactory->create()
                        ->setNumber($number)
                        ->setCarrierCode($carrierCode)
                        ->setTitle($carrierTitle)
                );
            }
        }
    }

    /**
     * Combine array of labels as instance PDF
     *
     * @param array $labelsContent
     * @return \Zend_Pdf
     */
    public function combineLabelsPdf(array $labelsContent)
    {
        $outputPdf = new \Zend_Pdf();
        foreach ($labelsContent as $content) {
            if (stripos($content, '%PDF-') !== false) {
                $pdfLabel = \Zend_Pdf::parse($content);
                foreach ($pdfLabel->pages as $page) {
                    $outputPdf->pages[] = clone $page;
                }
            } else {
                $page = $this->createPdfPageFromImageString($content);
                if ($page) {
                    $outputPdf->pages[] = $page;
                }
            }
        }
        return $outputPdf;
    }

    /**
     * Create \Zend_Pdf_Page instance with image from $imageString. Supports JPEG, PNG, GIF, WBMP, and GD2 formats.
     *
     * @param string $imageString
     * @return \Zend_Pdf_Page|false
     */
    public function createPdfPageFromImageString($imageString)
    {
        /** @var \Magento\Framework\Filesystem\Directory\Write $directory */
        $directory = $this->filesystem->getDirectoryWrite(
            DirectoryList::TMP
        );
        $directory->create();
        $image = @imagecreatefromstring($imageString);
        if (!$image) {
            return false;
        }

        $xSize = imagesx($image);
        $ySize = imagesy($image);
        $page = new \Zend_Pdf_Page($xSize, $ySize);

        imageinterlace($image, 0);
        $tmpFileName = $directory->getAbsolutePath(
            'shipping_labels_' . uniqid(\Magento\Framework\Math\Random::getRandomNumber()) . time() . '.png'
        );
        imagepng($image, $tmpFileName);
        $pdfImage = \Zend_Pdf_Image::imageWithPath($tmpFileName);
        $page->drawImage($pdfImage, 0, 0, $xSize, $ySize);
        $directory->delete($directory->getRelativePath($tmpFileName));
        if (is_resource($image)) {
            imagedestroy($image);
        }
        return $page;
    }
}