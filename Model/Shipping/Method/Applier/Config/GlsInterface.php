<?php

namespace ShoppingFeed\Gls\Model\Shipping\Method\Applier\Config;

use Magento\Framework\DataObject;
use ShoppingFeed\Manager\Model\Shipping\Method\Applier\ConfigInterface;

interface GlsInterface extends ConfigInterface
{
    /**
     * @param DataObject $configData
     * @return bool
     */
    public function isRelayPointDeliveryEnabled(DataObject $configData);

    /**
     * @param DataObject $configData
     * @return bool
     */
    public function isExpressDeliveryEnabled(DataObject $configData);

    /**
     * @param DataObject $configData
     * @return bool
     */
    public function isHomePlusDeliveryEnabled(DataObject $configData);

    /**
     * @param DataObject $configData
     * @return bool
     */
    public function isHomeDeliveryEnabled(DataObject $configData);

    /**
     * @param DataObject $configData
     * @return bool
     */
    public function shouldCheckRelayPointIdsWithGlsApi(DataObject $configData);

    /**
     * @param DataObject $configData
     * @return bool
     */
    public function shouldImportMissingRelayPointNamesFromGlsApi(DataObject $configData);
}
