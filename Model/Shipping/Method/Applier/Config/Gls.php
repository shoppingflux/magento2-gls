<?php

namespace ShoppingFeed\Gls\Model\Shipping\Method\Applier\Config;

use Magento\Framework\DataObject;
use ShoppingFeed\Manager\Model\Config\Field\Checkbox;
use ShoppingFeed\Manager\Model\Shipping\Method\Applier\AbstractConfig;

class Gls extends AbstractConfig implements GlsInterface
{
    const KEY_IS_RELAY_POINT_DELIVERY_ENABLED = 'is_relay_point_delivery_enabled';
    const KEY_IS_EXPRESS_DELIVERY_ENABLED = 'is_express_delivery_enabled';
    const KEY_IS_HOME_PLUS_DELIVERY_ENABLED = 'is_home_plus_delivery_enabled';
    const KEY_IS_HOME_DELIVERY_ENABLED = 'is_home_delivery_enabled';
    const KEY_CHECK_RELAY_POINT_IDS_WITH_GLS_API = 'check_relay_point_ids_with_gls_api';

    protected function getBaseFields()
    {
        $basicDeliveryNotice = __('Enabled options above have priority.');

        return array_merge(
            [
                $this->fieldFactory->create(
                    Checkbox::TYPE_CODE,
                    [
                        'name' => self::KEY_IS_RELAY_POINT_DELIVERY_ENABLED,
                        'isRequired' => true,
                        'label' => __('Enable Pickup Point Delivery'),
                        'checkedNotice' => __('Applied when a valid relay point ID is available.'),
                        'uncheckedNotice' => __('Enable to apply when a valid relay point ID is available.'),
                        'isCheckedByDefault' => true,
                        'checkedDependentFieldNames' => [ self::KEY_CHECK_RELAY_POINT_IDS_WITH_GLS_API ],
                        'sortOrder' => 100,
                    ]
                ),
                $this->fieldFactory->create(
                    Checkbox::TYPE_CODE,
                    [
                        'name' => self::KEY_CHECK_RELAY_POINT_IDS_WITH_GLS_API,
                        'isRequired' => true,
                        'label' => __('Check Pickup Point IDs Using the GLS API'),
                        'checkedNotice' => __('Pickup point IDs will be checked using the GLS API.'),
                        'uncheckedNotice' => __('Pickup point IDs made of 10 digits will be considered valid.'),
                        'isCheckedByDefault' => true,
                        'sortOrder' => 200,
                    ]
                ),
                $this->fieldFactory->create(
                    Checkbox::TYPE_CODE,
                    [
                        'name' => self::KEY_IS_EXPRESS_DELIVERY_ENABLED,
                        'isRequired' => true,
                        'label' => __('Enable Express Delivery'),
                        'checkedNotice' => __('Applied when the shipping address is compatible.')
                            . ' '
                            . __('Enabled options above have priority.'),
                        'uncheckedNotice' => __('Enable to apply when the shipping address is compatible.')
                            . ' '
                            . ('Enabled options above have priority.'),
                        'isCheckedByDefault' => true,
                        'sortOrder' => 300,
                    ]
                ),
                $this->fieldFactory->create(
                    Checkbox::TYPE_CODE,
                    [
                        'name' => self::KEY_IS_HOME_PLUS_DELIVERY_ENABLED,
                        'isRequired' => true,
                        'label' => __('Enable Home Plus Delivery'),
                        'checkedNotice' => $basicDeliveryNotice,
                        'uncheckedNotice' => $basicDeliveryNotice,
                        'isCheckedByDefault' => true,
                        'sortOrder' => 400,
                    ]
                ),
                $this->fieldFactory->create(
                    Checkbox::TYPE_CODE,
                    [
                        'name' => self::KEY_IS_HOME_DELIVERY_ENABLED,
                        'isRequired' => true,
                        'label' => __('Enable Home Delivery'),
                        'checkedNotice' => $basicDeliveryNotice,
                        'uncheckedNotice' => $basicDeliveryNotice,
                        'isCheckedByDefault' => true,
                        'sortOrder' => 500,
                    ]
                ),
            ],
            parent::getBaseFields()
        );
    }

    /**
     * @return string
     */
    protected function getBaseDefaultCarrierTitle()
    {
        return 'GLS';
    }

    /**
     * @return string
     */
    protected function getBaseDefaultMethodTitle()
    {
        return 'Delivery';
    }

    public function isRelayPointDeliveryEnabled(DataObject $configData)
    {
        return (bool) $this->getFieldValue(static::KEY_IS_RELAY_POINT_DELIVERY_ENABLED, $configData);
    }

    public function isExpressDeliveryEnabled(DataObject $configData)
    {
        return (bool) $this->getFieldValue(static::KEY_IS_EXPRESS_DELIVERY_ENABLED, $configData);
    }

    public function isHomePlusDeliveryEnabled(DataObject $configData)
    {
        return (bool) $this->getFieldValue(static::KEY_IS_HOME_PLUS_DELIVERY_ENABLED, $configData);
    }

    public function isHomeDeliveryEnabled(DataObject $configData)
    {
        return (bool) $this->getFieldValue(static::KEY_IS_HOME_DELIVERY_ENABLED, $configData);
    }

    public function shouldCheckRelayPointIdsWithGlsApi(DataObject $configData)
    {
        return (bool) $this->getFieldValue(static::KEY_CHECK_RELAY_POINT_IDS_WITH_GLS_API, $configData);
    }
}
