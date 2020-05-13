<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace BiFang\SubmitUpsShipment\Model;

use Magento\Framework\App\ObjectManager;
use Magento\Quote\Model\Quote\Address\RateResult\Error;
use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Shipping\Model\Carrier\AbstractCarrierOnline;
use Magento\Shipping\Model\Carrier\CarrierInterface;
use Magento\Shipping\Model\Rate\Result;
use Magento\Shipping\Model\Simplexml\Element;
use Magento\Ups\Helper\Config;
use Magento\Framework\Xml\Security;
use Magento\Framework\HTTP\ClientFactory;

/**
 * UPS shipping implementation
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class Carrier extends \Magento\Ups\Model\Carrier
{
    /**
     * Code of the carrier
     *
     * @var string
     */
    const CODE = 'ups';

    /**
     * Delivery Confirmation level based on origin/destination
     */
    const DELIVERY_CONFIRMATION_SHIPMENT = 1;

    const DELIVERY_CONFIRMATION_PACKAGE = 2;

    /**
     * Code of the carrier
     *
     * @var string
     */
    protected $_code = self::CODE;

    /**
     * Rate request data
     *
     * @var RateRequest
     */
    protected $_request;

    /**
     * Rate result data
     *
     * @var Result
     */
    protected $_result;

    /**
     * Base currency rate
     *
     * @var float
     */
    protected $_baseCurrencyRate;

    /**
     * Xml access request
     *
     * @var string
     */
    protected $_xmlAccessRequest;

    /**
     * Default cgi gateway url
     *
     * @var string
     */
    protected $_defaultCgiGatewayUrl = 'https://www.ups.com/using/services/rave/qcostcgi.cgi';

    /**
     * Test urls for shipment
     *
     * @var array
     */
    protected $_defaultUrls = [
        'ShipConfirm' => 'https://wwwcie.ups.com/ups.app/xml/ShipConfirm',
        'ShipAccept' => 'https://wwwcie.ups.com/ups.app/xml/ShipAccept',
    ];

    /**
     * Live urls for shipment
     *
     * @var array
     */
    protected $_liveUrls = [
        'ShipConfirm' => 'https://onlinetools.ups.com/ups.app/xml/ShipConfirm',
        'ShipAccept' => 'https://onlinetools.ups.com/ups.app/xml/ShipAccept',
    ];

    /**
     * Container types that could be customized for UPS carrier
     *
     * @var string[]
     */
    protected $_customizableContainerTypes = ['CP', 'CSP'];

    /**
     * @var \Magento\Framework\Locale\FormatInterface
     */
    protected $_localeFormat;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $_logger;

    /**
     * @var Config
     */
    protected $configHelper;

    /**
     * @inheritdoc
     */
    protected $_debugReplacePrivateDataKeys = [
        'UserId', 'Password', 'AccessLicenseNumber'
    ];

    /**
     * @var ClientFactory
     */
    private $httpClientFactory;

    /**
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory $rateErrorFactory
     * @param \Psr\Log\LoggerInterface $logger
     * @param Security $xmlSecurity
     * @param \Magento\Shipping\Model\Simplexml\ElementFactory $xmlElFactory
     * @param \Magento\Shipping\Model\Rate\ResultFactory $rateFactory
     * @param \Magento\Quote\Model\Quote\Address\RateResult\MethodFactory $rateMethodFactory
     * @param \Magento\Shipping\Model\Tracking\ResultFactory $trackFactory
     * @param \Magento\Shipping\Model\Tracking\Result\ErrorFactory $trackErrorFactory
     * @param \Magento\Shipping\Model\Tracking\Result\StatusFactory $trackStatusFactory
     * @param \Magento\Directory\Model\RegionFactory $regionFactory
     * @param \Magento\Directory\Model\CountryFactory $countryFactory
     * @param \Magento\Directory\Model\CurrencyFactory $currencyFactory
     * @param \Magento\Directory\Helper\Data $directoryData
     * @param \Magento\CatalogInventory\Api\StockRegistryInterface $stockRegistry
     * @param \Magento\Framework\Locale\FormatInterface $localeFormat
     * @param Config $configHelper
     * @param array $data
     * @param ClientFactory|null $httpClientFactory
     *
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory $rateErrorFactory,
        \Psr\Log\LoggerInterface $logger,
        Security $xmlSecurity,
        \Magento\Shipping\Model\Simplexml\ElementFactory $xmlElFactory,
        \Magento\Shipping\Model\Rate\ResultFactory $rateFactory,
        \Magento\Quote\Model\Quote\Address\RateResult\MethodFactory $rateMethodFactory,
        \Magento\Shipping\Model\Tracking\ResultFactory $trackFactory,
        \Magento\Shipping\Model\Tracking\Result\ErrorFactory $trackErrorFactory,
        \Magento\Shipping\Model\Tracking\Result\StatusFactory $trackStatusFactory,
        \Magento\Directory\Model\RegionFactory $regionFactory,
        \Magento\Directory\Model\CountryFactory $countryFactory,
        \Magento\Directory\Model\CurrencyFactory $currencyFactory,
        \Magento\Directory\Helper\Data $directoryData,
        \Magento\CatalogInventory\Api\StockRegistryInterface $stockRegistry,
        \Magento\Framework\Locale\FormatInterface $localeFormat,
        Config $configHelper,
        array $data = [],
        ClientFactory $httpClientFactory = null
    ) {
        parent::__construct(
            $scopeConfig,
            $rateErrorFactory,
            $logger,
            $xmlSecurity,
            $xmlElFactory,
            $rateFactory,
            $rateMethodFactory,
            $trackFactory,
            $trackErrorFactory,
            $trackStatusFactory,
            $regionFactory,
            $countryFactory,
            $currencyFactory,
            $directoryData,
            $stockRegistry,
            $localeFormat,
            $configHelper,
            $data
        );
        $this->httpClientFactory = $httpClientFactory ?: ObjectManager::getInstance()->get(ClientFactory::class);

        $this->zend_logger = new \Zend\Log\Logger();
        $this->zend_logger->addWriter(new \Zend\Log\Writer\Stream(BP . '/var/log/ups.log'));

    }

    /**
     * Map currency alias to currency code
     *
     * @param string $code
     * @return string
     */
    private function mapCurrencyCode($code)
    {
        $currencyMapping = [
            'RMB' => 'CNY',
            'CNH' => 'CNY'
        ];

        return isset($currencyMapping[$code]) ? $currencyMapping[$code] : $code;
    }


    /**
     * Processing rate for ship element
     *
     * @param \Magento\Framework\Simplexml\Element $shipElement
     * @param array $allowedMethods
     * @param array $allowedCurrencies
     * @param array $costArr
     * @param array $priceArr
     * @param bool $negotiatedActive
     * @param string $errorTitle
     * @param \Magento\Framework\Simplexml\Config $xml
     * @return void
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    private function processShippingRateForItem(
        \Magento\Framework\Simplexml\Element $shipElement,
        array $allowedMethods,
        array $allowedCurrencies,
        array &$costArr,
        array &$priceArr,
        $negotiatedActive,
        &$errorTitle
    ) {
        // @codingStandardsIgnoreStart
        $code = (string)$shipElement->Service->Code;
        if (in_array($code, $allowedMethods)) {
            if ($negotiatedActive) {
                $cost = $shipElement->NegotiatedRates->NetSummaryCharges->GrandTotal->MonetaryValue;
            } else {
                $cost = $shipElement->TotalCharges->MonetaryValue;
            }

            //convert price with Origin country currency code to base currency code
            $successConversion = true;
            $responseCurrencyCode = $this->mapCurrencyCode(
                (string)$shipElement->TotalCharges->CurrencyCode
            );
            // @codingStandardsIgnoreEnd

            if ($responseCurrencyCode) {
                if (in_array($responseCurrencyCode, $allowedCurrencies)) {
                    $cost = (double)$cost * $this->_getBaseCurrencyRate($responseCurrencyCode);
                } else {
                    $errorTitle = __(
                        'We can\'t convert a rate from "%1-%2".',
                        $responseCurrencyCode,
                        $this->_request->getPackageCurrency()->getCode()
                    );
                    $error = $this->_rateErrorFactory->create();
                    $error->setCarrier('ups');
                    $error->setCarrierTitle($this->getConfigData('title'));
                    $error->setErrorMessage($errorTitle);
                    $successConversion = false;
                }
            }

            if ($successConversion) {
                $costArr[$code] = $cost;
                $priceArr[$code] = $this->getMethodPrice((float)$cost, $code);
            }
        }
    }

    /**
     * Process tracking info from activity tag
     *
     * @param \Magento\Framework\Simplexml\Element $activityTag
     * @param int $index
     * @param array $resultArr
     * @return void
     */
    private function processActivityTagInfo(
        \Magento\Framework\Simplexml\Element $activityTag,
        $index,
        array &$resultArr
    ) {
        $addressArr = [];
        // @codingStandardsIgnoreStart
        if (isset($activityTag->ActivityLocation->Address->City)) {
            $addressArr[] = (string)$activityTag->ActivityLocation->Address->City;
        }
        if (isset($activityTag->ActivityLocation->Address->StateProvinceCode)) {
            $addressArr[] = (string)$activityTag->ActivityLocation->Address->StateProvinceCode;
        }
        if (isset($activityTag->ActivityLocation->Address->CountryCode)) {
            $addressArr[] = (string)$activityTag->ActivityLocation->Address->CountryCode;
        }

        $dateArr = [];
        $date = (string)$activityTag->Date;
        //YYYYMMDD
        $dateArr[] = substr($date, 0, 4);
        $dateArr[] = substr($date, 4, 2);
        $dateArr[] = substr($date, -2, 2);

        $timeArr = [];
        $time = (string)$activityTag->Time;
        // @codingStandardsIgnoreEnd
        //HHMMSS
        $timeArr[] = substr($time, 0, 2);
        $timeArr[] = substr($time, 2, 2);
        $timeArr[] = substr($time, -2, 2);

        if ($index === 1) {
            // @codingStandardsIgnoreStart
            $resultArr['status'] = (string)$activityTag->Status->StatusType->Description;
            $resultArr['deliverydate'] = implode('-', $dateArr);
            //YYYY-MM-DD
            $resultArr['deliverytime'] = implode(':', $timeArr);
            //HH:MM:SS
            $resultArr['deliverylocation'] = (string)$activityTag->ActivityLocation->Description;
            $resultArr['signedby'] = (string)$activityTag->ActivityLocation->SignedForByName;
            // @codingStandardsIgnoreEnd
            if ($addressArr) {
                $resultArr['deliveryto'] = implode(', ', $addressArr);
            }
        } else {
            $tempArr = [];
            // @codingStandardsIgnoreStart
            $tempArr['activity'] = (string)$activityTag->Status->StatusType->Description;
            // @codingStandardsIgnoreEnd
            $tempArr['deliverydate'] = implode('-', $dateArr);
            //YYYY-MM-DD
            $tempArr['deliverytime'] = implode(':', $timeArr);
            //HH:MM:SS
            if ($addressArr) {
                $tempArr['deliverylocation'] = implode(', ', $addressArr);
            }
            $resultArr['progressdetail'][] = $tempArr;
        }
    }

    /**
     * Form XML for shipment request
     *
     * @param \Magento\Framework\DataObject $request
     * @return string
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    protected function _formShipmentRequest(\Magento\Framework\DataObject $request)
    {
        $this->zend_logger->info("Extended Carrier");

        $packageParams = $request->getPackageParams();
        if ($packageParams->getDangerousGoods()) {
          $this->zend_logger->info($packageParams->toJson());
        }



        $height = $packageParams->getHeight();
        $width = $packageParams->getWidth();
        $length = $packageParams->getLength();
        $weightUnits = $packageParams->getWeightUnits() == \Zend_Measure_Weight::POUND ? 'LBS' : 'KGS';
        $dimensionsUnits = $packageParams->getDimensionUnits() == \Zend_Measure_Length::INCH ? 'IN' : 'CM';

        $itemsDesc = [];
        $itemsShipment = $request->getPackageItems();
        foreach ($itemsShipment as $itemShipment) {
            $item = new \Magento\Framework\DataObject();
            $item->setData($itemShipment);
            $itemsDesc[] = $item->getName();
        }

        $xmlRequest = $this->_xmlElFactory->create(
            ['data' => '<?xml version = "1.0" ?><ShipmentConfirmRequest xml:lang="en-US"/>']
        );
        $requestPart = $xmlRequest->addChild('Request');
        $requestPart->addChild('RequestAction', 'ShipConfirm');
        $requestPart->addChild('RequestOption', 'nonvalidate');

        $shipmentPart = $xmlRequest->addChild('Shipment');
        if ($request->getIsReturn()) {
            $returnPart = $shipmentPart->addChild('ReturnService');
            // UPS Print Return Label
            $returnPart->addChild('Code', '9');
        }
        $shipmentPart->addChild('Description', substr(implode(' ', $itemsDesc), 0, 35));
        //empirical

        $shipperPart = $shipmentPart->addChild('Shipper');
        if ($request->getIsReturn()) {
            $shipperPart->addChild('Name', $request->getRecipientContactCompanyName());
            $shipperPart->addChild('AttentionName', $request->getRecipientContactPersonName());
            $shipperPart->addChild('ShipperNumber', $this->getConfigData('shipper_number'));
            $shipperPart->addChild('PhoneNumber', $request->getRecipientContactPhoneNumber());

            $addressPart = $shipperPart->addChild('Address');
            $addressPart->addChild('AddressLine1', $request->getRecipientAddressStreet());
            $addressPart->addChild('AddressLine2', $request->getRecipientAddressStreet2());
            $addressPart->addChild('City', $request->getRecipientAddressCity());
            $addressPart->addChild('CountryCode', $request->getRecipientAddressCountryCode());
            $addressPart->addChild('PostalCode', $request->getRecipientAddressPostalCode());
            if ($request->getRecipientAddressStateOrProvinceCode()) {
                $addressPart->addChild('StateProvinceCode', $request->getRecipientAddressStateOrProvinceCode());
            }
        } else {
            $shipperPart->addChild('Name', $request->getShipperContactCompanyName());
            $shipperPart->addChild('AttentionName', $request->getShipperContactPersonName());
            $shipperPart->addChild('ShipperNumber', $this->getConfigData('shipper_number'));
            $shipperPart->addChild('PhoneNumber', $request->getShipperContactPhoneNumber());

            $addressPart = $shipperPart->addChild('Address');
            $addressPart->addChild('AddressLine1', $request->getShipperAddressStreet());
            $addressPart->addChild('AddressLine2', $request->getShipperAddressStreet2());
            $addressPart->addChild('City', $request->getShipperAddressCity());
            $addressPart->addChild('CountryCode', $request->getShipperAddressCountryCode());
            $addressPart->addChild('PostalCode', $request->getShipperAddressPostalCode());
            if ($request->getShipperAddressStateOrProvinceCode()) {
                $addressPart->addChild('StateProvinceCode', $request->getShipperAddressStateOrProvinceCode());
            }
        }

        $shipToPart = $shipmentPart->addChild('ShipTo');
        $shipToPart->addChild('AttentionName', $request->getRecipientContactPersonName());
        $shipToPart->addChild(
            'CompanyName',
            $request->getRecipientContactCompanyName() ? $request->getRecipientContactCompanyName() : 'N/A'
        );
        $shipToPart->addChild('PhoneNumber', $request->getRecipientContactPhoneNumber());

        $addressPart = $shipToPart->addChild('Address');
        $addressPart->addChild('AddressLine1', $request->getRecipientAddressStreet1());
        $addressPart->addChild('AddressLine2', $request->getRecipientAddressStreet2());
        $addressPart->addChild('City', $request->getRecipientAddressCity());
        $addressPart->addChild('CountryCode', $request->getRecipientAddressCountryCode());
        $addressPart->addChild('PostalCode', $request->getRecipientAddressPostalCode());
        if ($request->getRecipientAddressStateOrProvinceCode()) {
            $addressPart->addChild('StateProvinceCode', $request->getRecipientAddressRegionCode());
        }
        if ($this->getConfigData('dest_type') == 'RES') {
            $addressPart->addChild('ResidentialAddress');
        }

        if ($request->getIsReturn()) {
            $shipFromPart = $shipmentPart->addChild('ShipFrom');
            $shipFromPart->addChild('AttentionName', $request->getShipperContactPersonName());
            $shipFromPart->addChild(
                'CompanyName',
                $request->getShipperContactCompanyName() ? $request
                    ->getShipperContactCompanyName() : $request
                    ->getShipperContactPersonName()
            );
            $shipFromAddress = $shipFromPart->addChild('Address');
            $shipFromAddress->addChild('AddressLine1', $request->getShipperAddressStreet1());
            $shipFromAddress->addChild('AddressLine2', $request->getShipperAddressStreet2());
            $shipFromAddress->addChild('City', $request->getShipperAddressCity());
            $shipFromAddress->addChild('CountryCode', $request->getShipperAddressCountryCode());
            $shipFromAddress->addChild('PostalCode', $request->getShipperAddressPostalCode());
            if ($request->getShipperAddressStateOrProvinceCode()) {
                $shipFromAddress->addChild('StateProvinceCode', $request->getShipperAddressStateOrProvinceCode());
            }

            $addressPart = $shipToPart->addChild('Address');
            $addressPart->addChild('AddressLine1', $request->getShipperAddressStreet1());
            $addressPart->addChild('AddressLine2', $request->getShipperAddressStreet2());
            $addressPart->addChild('City', $request->getShipperAddressCity());
            $addressPart->addChild('CountryCode', $request->getShipperAddressCountryCode());
            $addressPart->addChild('PostalCode', $request->getShipperAddressPostalCode());
            if ($request->getShipperAddressStateOrProvinceCode()) {
                $addressPart->addChild('StateProvinceCode', $request->getShipperAddressStateOrProvinceCode());
            }
            if ($this->getConfigData('dest_type') == 'RES') {
                $addressPart->addChild('ResidentialAddress');
            }
        }

        $servicePart = $shipmentPart->addChild('Service');
        $servicePart->addChild('Code', $request->getShippingMethod());
        $packagePart = $shipmentPart->addChild('Package');
        $packagePart->addChild('Description', substr(implode(' ', $itemsDesc), 0, 35));
        //empirical
        $packagePart->addChild('PackagingType')->addChild('Code', $request->getPackagingType());
        $packageWeight = $packagePart->addChild('PackageWeight');
        $packageWeight->addChild('Weight', $request->getPackageWeight());
        $packageWeight->addChild('UnitOfMeasurement')->addChild('Code', $weightUnits);

        // set dimensions
        if ($length || $width || $height) {
            $packageDimensions = $packagePart->addChild('Dimensions');
            $packageDimensions->addChild('UnitOfMeasurement')->addChild('Code', $dimensionsUnits);
            $packageDimensions->addChild('Length', $length);
            $packageDimensions->addChild('Width', $width);
            $packageDimensions->addChild('Height', $height);
        }

        // ups support reference number only for domestic service
        if ($this->_isUSCountry(
                $request->getRecipientAddressCountryCode()
            ) && $this->_isUSCountry(
                $request->getShipperAddressCountryCode()
            )
        ) {
            if ($request->getReferenceData()) {
                $referenceData = $request->getReferenceData() . $request->getPackageId();
            } else {
                $referenceData = 'Order #' .
                    $request->getOrderShipment()->getOrder()->getIncrementId() .
                    ' P' .
                    $request->getPackageId();
            }
            $referencePart = $packagePart->addChild('ReferenceNumber');
            $referencePart->addChild('Code', '02');
            $referencePart->addChild('Value', $referenceData);
        }

        if ($packageParams->getDangerousGoods()) {
          $packageServiceOptionsPart = $packagePart->addChild('PackageServiceOptions');
          $hazMatPart = $packageServiceOptionsPart->addChild('HazMat');
          $hazMatPart->addChild('ProperShippingName', 'Lithium ion batteries');
          $hazMatPart->addChild('RegulationSet', 'ADR');
          $hazMatPart->addChild('TransportationMode', 'Ground');
        }

        $deliveryConfirmation = $packageParams->getDeliveryConfirmation();
        if ($deliveryConfirmation) {
            /** @var $serviceOptionsNode Element */
            $serviceOptionsNode = null;
            switch ($this->_getDeliveryConfirmationLevel($request->getRecipientAddressCountryCode())) {
                case self::DELIVERY_CONFIRMATION_PACKAGE:
                    $serviceOptionsNode = $packagePart->addChild('PackageServiceOptions');
                    break;
                case self::DELIVERY_CONFIRMATION_SHIPMENT:
                    $serviceOptionsNode = $shipmentPart->addChild('ShipmentServiceOptions');
                    break;
                default:
                    break;
            }
            if (!is_null($serviceOptionsNode)) {
                $serviceOptionsNode->addChild(
                    'DeliveryConfirmation'
                )->addChild(
                    'DCISType',
                    $packageParams->getDeliveryConfirmation()
                );
            }
        }

        $shipmentPart->addChild(
            'PaymentInformation'
        )->addChild(
            'Prepaid'
        )->addChild(
            'BillShipper'
        )->addChild(
            'AccountNumber',
            $this->getConfigData('shipper_number')
        );

        // We add DGSignatoryInfo here for dangerous goods
        if ($packageParams->getDangerousGoods()) {
          $dGSignatoryInfoPart = $shipmentPart->addChild('DGSignatoryInfo');
          $dGSignatoryInfoPart->addChild('Name', 'Stefan');
          $dGSignatoryInfoPart->addChild('Title', 'Manager');
          $dGSignatoryInfoPart->addChild('Place', 'Tettnang');
          $dGSignatoryInfoPart->addChild('Date', '20200512');
          $dGSignatoryInfoPart->addChild('ShipperDeclaration', '01');
        }



        if ($request->getPackagingType() != $this->configHelper->getCode(
                'container',
                'ULE'
            ) &&
            $request->getShipperAddressCountryCode() == self::USA_COUNTRY_ID &&
            ($request->getRecipientAddressCountryCode() == 'CA' ||
                $request->getRecipientAddressCountryCode() == 'PR')
        ) {
            $invoiceLineTotalPart = $shipmentPart->addChild('InvoiceLineTotal');
            $invoiceLineTotalPart->addChild('CurrencyCode', $request->getBaseCurrencyCode());
            $invoiceLineTotalPart->addChild('MonetaryValue', ceil($packageParams->getCustomsValue()));
        }

        $labelPart = $xmlRequest->addChild('LabelSpecification');
        $labelPart->addChild('LabelPrintMethod')->addChild('Code', 'GIF');
        $labelPart->addChild('LabelImageFormat')->addChild('Code', 'GIF');

        // Magento 2.1.18
        // return $xmlRequest->asXml();

        // Magento 2.1.11
        $this->setXMLAccessRequest();
        $xmlRequest = $this->_xmlAccessRequest . $xmlRequest->asXml();
        return $xmlRequest;
    }

    /**
     * Send and process shipment accept request
     *
     * @param Element $shipmentConfirmResponse
     * @return \Magento\Framework\DataObject
     */
    protected function _sendShipmentAcceptRequest(Element $shipmentConfirmResponse)
    {
        $this->zend_logger->info("ShipmentAcceptRequest send?");
        $xmlRequest = $this->_xmlElFactory->create(
            ['data' => '<?xml version = "1.0" ?><ShipmentAcceptRequest/>']
        );
        $request = $xmlRequest->addChild('Request');
        $request->addChild('RequestAction', 'ShipAccept');
        $xmlRequest->addChild('ShipmentDigest', $shipmentConfirmResponse->ShipmentDigest);
        $debugData = ['request' => $xmlRequest->asXML()];

        try {
            $ch = curl_init($this->getShipAcceptUrl());
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $this->_xmlAccessRequest . $xmlRequest->asXML());
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, (bool)$this->getConfigFlag('mode_xml'));
            $xmlResponse = curl_exec($ch);

            $debugData['result'] = $xmlResponse;
            $this->_setCachedQuotes($xmlRequest, $xmlResponse);
        } catch (\Exception $e) {
            $debugData['result'] = ['error' => $e->getMessage(), 'code' => $e->getCode()];
            $xmlResponse = '';
        }

        try {
            $response = $this->_xmlElFactory->create(['data' => $xmlResponse]);
        } catch (\Exception $e) {
            $debugData['result'] = ['error' => $e->getMessage(), 'code' => $e->getCode()];
        }

        $result = new \Magento\Framework\DataObject();
        if (isset($response->Error)) {
            $result->setErrors((string)$response->Error->ErrorDescription);
        } else {
            $shippingLabelContent = (string)$response->ShipmentResults->PackageResults->LabelImage->GraphicImage;
            if (property_exists($response->ShipmentResults, 'DGPaperImage')) {
                $shippingDGPaperImage = (string)$response->ShipmentResults->DGPaperImage;
                $result->setDangerousGoodsPaper(base64_decode($shippingLabelContent));
            }

            $trackingNumber = (string)$response->ShipmentResults->PackageResults->TrackingNumber;

            $result->setShippingLabelContent(base64_decode($shippingLabelContent));
            $result->setTrackingNumber($trackingNumber);
        }

        $this->_debug($debugData);

        return $result;
    }
}
