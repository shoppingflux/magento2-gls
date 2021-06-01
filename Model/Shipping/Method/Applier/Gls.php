<?php

namespace ShoppingFeed\Gls\Model\Shipping\Method\Applier;

use Acyba\GLS\Helper\Tools as GlsHelper;
use Acyba\GLS\Model\Carrier\GLS as GlsCarrier;
use Acyba\GLS\Model\Webservice\Service as GlsWebService;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DataObject;
use Magento\Quote\Model\Quote\Address as QuoteAddress;
use ShoppingFeed\Gls\Model\Shipping\Method\Applier\Config\GlsInterface as ConfigInterface;
use ShoppingFeed\Manager\Api\Data\Marketplace\OrderInterface as MarketplaceOrderInterface;
use ShoppingFeed\Manager\Api\Data\Marketplace\Order\AddressInterface as MarketplaceAddressInterface;
use ShoppingFeed\Manager\Model\Shipping\Method\AbstractApplier;
use ShoppingFeed\Manager\Model\Shipping\Method\Applier\Result;
use ShoppingFeed\Manager\Model\Shipping\Method\Applier\ResultFactory;

/**
 * @method ConfigInterface getConfig()
 */
class Gls extends AbstractApplier
{
    const GLS_API_ENDPOINT_FIND_RELAY_POINT_BY_ID = 'GetParcelShopById';

    const CONFIG_GROUP_GLS = 'gls';
    const CONFIG_SECTION_GLS_CARRIERS = 'carriers';
    const CONFIG_FIELD_GLS_API_USERNAME = 'gls_usernamews';
    const CONFIG_FIELD_GLS_API_PASSWORD = 'gls_passws';

    const DELIVERY_BASE_CODE_RELAY = 'relay';
    const DELIVERY_BASE_CODE_EXPRESS = 'express';
    const DELIVERY_BASE_CODE_HOME_PLUS = 'fds';
    const DELIVERY_BASE_CODE_HOME = 'tohome';

    const SESSION_KEY_GLS_RELAY_POINT_DATA = 'gls_relay_information';

    /**
     * @var ResourceConnection
     */
    private $resource;

    /**
     * @var CheckoutSession
     */
    private $checkoutSession;

    /**
     * @var GlsHelper
     */
    private $glsHelper;

    /**
     * @var GlsWebService
     */
    private $glsWebService;

    /**
     * @var array
     */
    private $relayPointData = [];

    /**
     * @param ConfigInterface $config
     * @param ResultFactory $resultFactory
     * @param ResourceConnection $resource
     * @param CheckoutSession $checkoutSession
     * @param GlsHelper $glsHelper
     * @param GlsWebService $glsService
     */
    public function __construct(
        ConfigInterface $config,
        ResultFactory $resultFactory,
        ResourceConnection $resource,
        CheckoutSession $checkoutSession,
        GlsHelper $glsHelper,
        GlsWebService $glsService
    ) {
        $this->resource = $resource;
        $this->checkoutSession = $checkoutSession;
        $this->glsHelper = $glsHelper;
        $this->glsWebService = $glsService;
        parent::__construct($config, $resultFactory);
    }

    /**
     * @return string
     */
    public function getLabel()
    {
        return 'GLS';
    }

    /**
     * @return string
     */
    public function getGlsAgencyCode()
    {
        return trim($this->glsHelper->getConfigValue('gls_agency_code', 'gls_general', 'gls_section'));
    }

    /**
     * @param QuoteAddress $quoteShippingAddress
     * @return bool
     */
    public function isExpressDeliveryAvailableFor(QuoteAddress $quoteShippingAddress)
    {
        $agencyCode = $this->getGlsAgencyCode();
        $postcode = trim($quoteShippingAddress->getPostcode());
        $countryId = strtoupper(trim($quoteShippingAddress->getCountryId()));

        if (empty($countryId)
            || empty($postcode)
            || empty($agencyCode)
            || ('FR' !== $countryId)
        ) {
            return false;
        }

        $connection = $this->resource->getConnection();

        $entryIdSelect = $connection->select()
            ->from($this->resource->getTableName('gls_agencies_list'), [ 'id_agency_entry' ])
            ->where('agencycode = ?', $agencyCode)
            ->where('zipcode_start <= ?', $postcode)
            ->where('zipcode_end >= ?', $postcode);

        $entryId = trim($connection->fetchOne($entryIdSelect));

        return !empty($entryId);
    }

    /**
     * @param string $relayPointId
     * @return array|false
     */
    private function getRelayPointData($relayPointId)
    {
        if (!isset($this->relayPointData[$relayPointId])) {
            $this->relayPointData[$relayPointId] = false;

            $relayPointId = trim($relayPointId);
            $soapClient = new \SoapClient($this->glsWebService->getUrlWsdl());

            $apiUserName = trim(
                $this->glsHelper->getConfigValue(
                    static::CONFIG_FIELD_GLS_API_USERNAME,
                    static::CONFIG_GROUP_GLS,
                    static::CONFIG_SECTION_GLS_CARRIERS
                )
            );

            $apiPassword = trim(
                $this->glsHelper->getConfigValue(
                    static::CONFIG_FIELD_GLS_API_PASSWORD,
                    static::CONFIG_GROUP_GLS,
                    static::CONFIG_SECTION_GLS_CARRIERS
                )
            );

            if (!empty($relayPointId) && !empty($apiUserName) && !empty($apiPassword)) {
                $result = $soapClient->__soapCall(
                    static::GLS_API_ENDPOINT_FIND_RELAY_POINT_BY_ID,
                    [
                        [
                            'Credentials' => [
                                'UserName' => $apiUserName,
                                'Password' => $apiPassword,
                            ],
                            'ParcelShopId' => $relayPointId,
                        ],
                    ]
                );

                $result = (array) json_decode(json_encode($result), true);

                if (trim($result['ParcelShop']['ParcelShopId'] ?? '') === $relayPointId) {
                    $this->relayPointData[$relayPointId] = $result;
                }
            }
        }

        return $this->relayPointData[$relayPointId];
    }

    /**
     * @param string $relayPointId
     * @return bool
     */
    private function isExistingRelayPointId($relayPointId)
    {
        return is_array($this->getRelayPointData($relayPointId));
    }

    /**
     * @param string $relayPointId
     * @param DataObject $configData
     * @return bool
     */
    private function isValidRelayPointId($relayPointId, DataObject $configData)
    {
        return $this->getConfig()->shouldCheckRelayPointIdsWithGlsApi($configData)
            ? $this->isExistingRelayPointId($relayPointId)
            : (bool) preg_match('/^[0-9]{10}$/', $relayPointId);
    }

    /**
     * @param string $baseCode
     * @param QuoteAddress $quoteShippingAddress
     * @return string|null
     */
    private function getAvailableMethodCodeByDeliveryBaseCode($baseCode, QuoteAddress $quoteShippingAddress)
    {
        $availableQualifiedMethodCodes = $this->getAvailableShippingMethodCodes($quoteShippingAddress);
        $qualifiedPrefix = GlsCarrier::CODE . '_';
        $methodCode = null;

        foreach ($availableQualifiedMethodCodes as $qualifiedMethodCode) {
            if (strpos($qualifiedMethodCode, $qualifiedPrefix . $baseCode) === 0) {
                $methodCode = substr($qualifiedMethodCode, strlen($qualifiedPrefix));
                break;
            }
        }

        return $methodCode;
    }

    /**
     * @param string $baseCode
     * @return string
     */
    public function getDefaultMethodCodeByDeliveryBaseCode($baseCode)
    {
        return $baseCode . '_shopping_feed';
    }

    public function applyToQuoteShippingAddress(
        MarketplaceOrderInterface $marketplaceOrder,
        MarketplaceAddressInterface $marketplaceShippingAddress,
        QuoteAddress $quoteShippingAddress,
        DataObject $configData
    ) {
        $config = $this->getConfig();
        $chosenMethodCode = null;
        $relayPointId = null;
        $isRelayPointDelivery = false;

        if ($config->isRelayPointDeliveryEnabled($configData)
            && !empty($relayPointId = trim($marketplaceShippingAddress->getMiscData()))
            && $this->isValidRelayPointId($relayPointId, $configData)
        ) {
            $chosenMethodCode = $this->getAvailableMethodCodeByDeliveryBaseCode(
                static::DELIVERY_BASE_CODE_RELAY,
                $quoteShippingAddress
            );

            // Do not consider other delivery methods if we have a relay point ID and availability is not required.
            if (empty($chosenMethodCode) && !$config->shouldOnlyApplyIfAvailable($configData)) {
                $chosenMethodCode = $this->getDefaultMethodCodeByDeliveryBaseCode(static::DELIVERY_BASE_CODE_RELAY);
            }
        }

        if (!empty($chosenMethodCode)) {
            $isRelayPointDelivery = true;
        } else {
            $appliableDeliveryBaseCodes = [];

            if ($config->isExpressDeliveryEnabled($configData)
                && $this->isExpressDeliveryAvailableFor($quoteShippingAddress)
            ) {
                $appliableDeliveryBaseCodes[] = static::DELIVERY_BASE_CODE_EXPRESS;
            }

            if ($config->isHomePlusDeliveryEnabled($configData)) {
                $appliableDeliveryBaseCodes[] = static::DELIVERY_BASE_CODE_HOME_PLUS;
            }

            if ($config->isHomeDeliveryEnabled($configData)) {
                $appliableDeliveryBaseCodes[] = static::DELIVERY_BASE_CODE_HOME;
            }

            if (empty($appliableDeliveryBaseCodes)) {
                return null;
            }

            foreach ($appliableDeliveryBaseCodes as $baseCode) {
                $chosenMethodCode = $this->getAvailableMethodCodeByDeliveryBaseCode($baseCode, $quoteShippingAddress);

                if (null !== $chosenMethodCode) {
                    break;
                }
            }

            if (null === $chosenMethodCode) {
                $baseCode = array_shift($appliableDeliveryBaseCodes);

                if (!empty($baseCode)) {
                    $chosenMethodCode = $this->getDefaultMethodCodeByDeliveryBaseCode($baseCode);
                }
            }
        }

        $result = null;

        if (!empty($chosenMethodCode)) {
            $result = $this->applyCarrierMethodToQuoteShippingAddress(
                GlsCarrier::CODE,
                $chosenMethodCode,
                $marketplaceOrder,
                $quoteShippingAddress,
                $configData
            );
        }

        if (($result instanceof Result) && $isRelayPointDelivery) {
            $result->setAdditionalData([ 'relay_point_id' => $relayPointId ]);
        }

        return $result;
    }

    public function commitOnQuoteShippingAddress(
        QuoteAddress $quoteShippingAddress,
        Result $result,
        DataObject $configData
    ) {
        $this->checkoutSession->setData(static::SESSION_KEY_GLS_RELAY_POINT_DATA, []);
        $additionalData = $result->getAdditionalData();

        if (isset($additionalData['relay_point_id'])) {
            // The GLS module will not apply the relay point data if any of the values is empty.
            $company = trim($quoteShippingAddress->getCompany());

            if (
                empty($company)
                && $this->getConfig()->shouldImportMissingRelayPointNamesFromGlsApi($configData)
            ) {
                $relayPointData = $this->getRelayPointData($additionalData['relay_point_id']);

                if (isset($relayPointData['ParcelShop']['Address']['Name1'])) {
                    $company = trim($relayPointData['ParcelShop']['Address']['Name1'] ?? '');
                }
            }

            if (empty($company)) {
                $company = '__';
            }

            $this->checkoutSession->setData(
                static::SESSION_KEY_GLS_RELAY_POINT_DATA,
                [
                    'id' => $additionalData['relay_point_id'],
                    'name' => $company,
                    'address' => $quoteShippingAddress->getStreet(),
                    'post_code' => $quoteShippingAddress->getPostcode(),
                    'city' => $quoteShippingAddress->getCity(),
                ]
            );
        }

        return $this;
    }
}
