<?php
/**
 * (c) shopware AG <info@shopware.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SwagImportExport\Components\DataManagers;

abstract class DataManager
{
    /**
     * Return fields which should be set by default
     *
     * @param array<string, array<string>> $defaultFields Contains default fields name and types
     *
     * @return array
     */
    public function getFields(array $defaultFields)
    {
        $defaultValues = [];
        foreach ($defaultFields as $type => $fields) {
            foreach ($fields as $field) {
                $defaultValues[] = $field;
            }
        }

        return $defaultValues;
    }

    /**
     * Return type of default field
     *
     * @return bool|int|string
     */
    public static function getFieldType(array $record, array $mapper)
    {
        foreach ($mapper as $type => $fields) {
            if (\in_array($record, $fields)) {
                return $type;
            }
        }

        return false;
    }

    /**
     * Cast default value to it proper type
     */
    public static function castDefaultValue(string $value, string $type)
    {
        switch ($type) {
            case 'id':
            case 'integer':
                return (int) $value;
                break;
            case 'boolean':
                return ($value == 'true') ? 1 : 0;
                break;
            default:
                return $value;
                break;
        }
    }

    /**
     * Return proper values for fields which have values NULL
     *
     * @return array<string, int|string>
     */
    public function fixFieldsValues(array $records, array $fieldsValues)
    {
        foreach ($fieldsValues as $type => $fields) {
            foreach ($fields as $field) {
                if (empty($records[$field])) {
                    switch ($type) {
                        case 'string':
                            $records[$field] = '';
                            break;
                        case 'int':
                            $records[$field] = '0';
                            break;
                        case 'float':
                            $records[$field] = '0.0';
                            break;
                        case 'date':
                            $records[$field] = \date('Y-m-d H:i:s');
                    }
                }
            }
        }

        return $records;
    }

    /**
     * Add columns which are missing because
     * doctrine property and database mismatch
     *
     * @param array<string, string|int> $records
     * @param array<string, string|int> $adapterFields
     *
     * @return array
     */
    public function mapFields(array $records, array $adapterFields)
    {
        foreach ($adapterFields as $tableField => $adapterField) {
            if (isset($records[$adapterField])) {
                $records[$tableField] = $records[$adapterField];
            }
        }

        return $records;
    }
}
