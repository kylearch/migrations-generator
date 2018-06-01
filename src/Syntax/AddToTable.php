<?php

namespace KyleArch\SchemaExporter\Syntax;

/**
 * Class AddToTable
 * @package Xethron\MigrationsGenerator\Syntax
 */
class AddToTable extends Table
{

    protected $pkTypes = ['bigIncrements', 'increments', 'mediumIncrements', 'smallIncrements', 'tinyIncrements', 'primary'];

    protected $convertFirstPK = false;

    /**
     * Return string for adding a column
     *
     * @param array $field
     *
     * @return string
     */
    protected function getItem(array $field)
    {
        $property = $field['field'];

        // If the field is an array,
        // make it an array in the Migration
        if (is_array($property)) {
            $property = "['" . implode("','", $property) . "']";
        } else {
            $property = $property ? "'$property'" : null;
        }

        $type = $field['type'];

        /*
         * Remove multiple PKs
         * Take advantage of the fact that Increments and Integer both start with 'I'
         */
        if ($this->convertFirstPK === true && strpos($type, 'ncrements') !== false) {
            $type = str_replace('ncrements', 'nteger', $type);

            $field['decorators'][] = 'unsigned';
        }

        // We don't use booleans
        if ($type === 'boolean') {
            $type = 'unsignedTinyInteger';
        }

        $output = sprintf("\$table->%s(%s)", $type, $property);

        // Make short PK name
        if ($field['type'] === 'primary' && is_array($field['field'])) {
            $field['args'] = "'" . implode("_", $field['field']) . "'";
        }

        // If we have args, then it needs
        // to be formatted a bit differently
        if (isset($field['args'])) {
            $output = sprintf("\$table->%s(%s, %s)", $type, $property, $field['args']);
        }
        if (isset($field['decorators'])) {
            $output .= $this->addDecorators($field['decorators']);
        }

        return $output . ';';
    }

    /**
     * Return string for adding all foreign keys
     *
     * @param array $items
     *
     * @return array
     */
    protected function getItems(array $items)
    {
        $pkCount = 0;
        $result  = [];

        // Count primary keys
        foreach ($items as $item) {
            if (in_array($item['type'], $this->pkTypes)) {
                $pkCount++;
            }
        }

        $this->convertFirstPK = $pkCount > 1;

        foreach ($items as $item) {
            $result[] = $this->getItem($item);
        }

        return $result;
    }
}
