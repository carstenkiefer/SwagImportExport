<?php
/**
 * (c) shopware AG <info@shopware.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SwagImportExport\Components\DataManagers;

use SwagImportExport\Components\DataType\CategoryDataType;

class CategoriesDataManager extends DataManager implements \Enlight_Hook
{
    /**
     * @return array
     */
    public function getDefaultFields()
    {
        return CategoryDataType::$defaultFieldsForCreate;
    }

    /**
     * Return fields which should be set by default
     *
     * @return array
     */
    public function getDefaultFieldsName()
    {
        $defaultFieldsForCreate = $this->getDefaultFields();
        $defaultFields = $this->getFields($defaultFieldsForCreate);

        return $defaultFields;
    }

    /**
     * Sets fields which are empty by default.
     *
     * @param array<string, string|int> $record
     * @param array<string, mixed>      $defaultValues
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
        }

        return $record;
    }
}
