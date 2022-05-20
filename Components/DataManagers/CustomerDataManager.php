<?php
/**
 * (c) shopware AG <info@shopware.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SwagImportExport\Components\DataManagers;

use Shopware\Components\NumberRangeIncrementerInterface;
use Shopware\Components\Password\Manager;
use SwagImportExport\Components\DataType\CustomerDataType;

class CustomerDataManager extends DataManager implements \Enlight_Hook
{
    private \Shopware_Components_Config $config;

    private \Enlight_Components_Db_Adapter_Pdo_Mysql $db;

    private Manager $passwordManager;

    private NumberRangeIncrementerInterface $numbergenerator;

    /**
     * initialises the class properties
     */
    public function __construct(
        \Enlight_Components_Db_Adapter_Pdo_Mysql $db,
        \Shopware_Components_Config $config,
        Manager $passwordManager,
        NumberRangeIncrementerInterface $numberRangeIncrementer
    ) {
        $this->db = $db;
        $this->config = $config;
        $this->passwordManager = $passwordManager;
        $this->numbergenerator = $numberRangeIncrementer;
    }

    /**
     * @return array
     */
    public function getDefaultFields()
    {
        return CustomerDataType::$defaultFieldsForCreate;
    }

    /**
     * Return fields which should be set by default
     *
     * @return array
     */
    public function getDefaultFieldsName()
    {
        $defaultFieldsForCreate = $this->getDefaultFields();

        return $this->getFields($defaultFieldsForCreate);
    }

    /**
     * Sets fields which are empty by default.
     *
     * @param array<string, string|int> $record
     * @param array<string, string|int> $defaultValues
     */
    public function setDefaultFieldsForCreate(array $record, array $defaultValues)
    {
        $getDefaultFields = $this->getDefaultFieldsName();
        foreach ($getDefaultFields as $key) {
            if (isset($record[$key])) {
                continue;
            }

            if (isset($defaultValues[$key])) {
                $record[$key] = $defaultValues[$key];
            }

            switch ($key) {
                case 'customernnumber':
                    if (!$record[$key]) {
                        $record[$key] = $this->getAutogeneratedCustomernumber();
                    }
                    break;
                case 'paymentID':
                    if (!$record[$key]) {
                        $record[$key] = $this->getPayment($record);
                    }
                    break;
                case 'encoder':
                    if (!$record[$key]) {
                        $record[$key] = $this->getEncoder();
                    }
                    break;
                case 'attrBillingText1':
                    $record[$key] = '';
                    break;
                case 'attrBillingText2':
                    $record[$key] = '';
                    break;
                case 'attrBillingText3':
                    $record[$key] = '';
                    break;
                case 'attrBillingText4':
                    $record[$key] = '';
                    break;
                case 'attrBillingText5':
                    $record[$key] = '';
                    break;
                case 'attrBillingText6':
                    $record[$key] = '';
                    break;
                case 'attrShippingText1':
                    $record[$key] = '';
                    break;
                case 'attrShippingText2':
                    $record[$key] = '';
                    break;
                case 'attrShippingText3':
                    $record[$key] = '';
                    break;
                case 'attrShippingText4':
                    $record[$key] = '';
                    break;
                case 'attrShippingText5':
                    $record[$key] = '';
                    break;
                case 'attrShippingText6':
                    $record[$key] = '';
                    break;
            }
        }

        $record = $this->fixDefaultValues($record);

        return $record;
    }

    /**
     * Return proper values for customer fields which have values NULL
     *
     * @param array<string, string|int> $records
     *
     * @return array
     */
    public function fixDefaultValues(array $records)
    {
        $defaultFieldsValues = CustomerDataType::$defaultFieldsValues;
        $records = $this->fixFieldsValues($records, $defaultFieldsValues);

        return $records;
    }

    /**
     * @param array<int|string, mixed> $record
     */
    private function getPayment(array $record)
    {
        if (!isset($record['subshopID'])) {
            return $this->config->get('sDEFAULTPAYMENT');
        }

        $subShopId = $record['subshopID'];

        // get defaultPaymentId for subShop
        $defaultPaymentId = $this->getSubShopDefaultPaymentId((int) $subShopId);
        if ($defaultPaymentId) {
            return \unserialize($defaultPaymentId);
        }

        // get defaultPaymentId for mainShop
        $defaultPaymentId = $this->getMainShopDefaultPaymentId((int) $subShopId);
        if ($defaultPaymentId) {
            return \unserialize($defaultPaymentId);
        }

        return $this->config->get('sDEFAULTPAYMENT');
    }

    /**
     * @return string
     */
    private function getSubShopDefaultPaymentId(int $subShopId)
    {
        $query = "SELECT `value`.value
                  FROM s_core_config_elements AS element
                  JOIN s_core_config_values AS `value` ON `value`.element_id = element.id
                  WHERE `value`.shop_id = ? AND element.name = 'defaultpayment'";

        return $this->db->fetchOne($query, [$subShopId]);
    }

    /**
     * @return string
     */
    private function getMainShopDefaultPaymentId(int $subShopId)
    {
        $query = "SELECT `value`.value
                  FROM s_core_config_elements AS element
                  JOIN s_core_config_values AS `value` ON `value`.element_id = element.id
                  WHERE `value`.shop_id = (SELECT main_id FROM s_core_shops WHERE id = ?)
                    AND element.name = 'defaultpayment'";

        return $this->db->fetchOne($query, [$subShopId]);
    }

    /**
     * @return string
     */
    private function getEncoder()
    {
        return $this->passwordManager->getDefaultPasswordEncoderName();
    }

    /**
     * @return string
     */
    private function getAutogeneratedCustomernumber()
    {
        return $this->config->get('shopwareManagedCustomerNumbers') ? $this->numbergenerator->increment('user') : '';
    }
}
