<?php

namespace KyleArch\SchemaExporter\Syntax;

class DroppedTable
{
    /**
     * Get string for dropping a table
     *
     * @param      $tableName
     * @param null $connection
     * @param bool $ifExists
     *
     * @return string
     */
    public function drop($tableName, $connection = null, $ifExists = true)
    {
        $dropType = $ifExists === true ? 'dropIfExists' : 'drop';
        if (!is_null($connection)) {
            $connection = 'connection(\'' . $connection . '\')->';
        }

        return "\t\tSchema::{$connection}{$dropType}('$tableName');";
    }

}
