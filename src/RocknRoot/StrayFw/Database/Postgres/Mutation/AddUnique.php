<?php

namespace RocknRoot\StrayFw\Database\Postgres\Mutation;

use RocknRoot\StrayFw\Database\Database;
use RocknRoot\StrayFw\Database\Helper;

/**
 * Representation for unique constraint addition operations.
 *
 * @author Nekith <nekith@errant-works.com>
 */
class AddUnique extends Mutation
{
    /**
     * Prepare and return according PDO statement.
     *
     * @param  Database     $database         database
     * @param  string       $modelName        model name
     * @param  string       $tableName        table real name
     * @param  array        $tableDefinition  table definition
     * @param  string       $uniqueName       unique constraint name
     * @return \PDOStatement $statement prepared query
     */
    public static function statement(Database $database, $modelName, $tableName, array $tableDefinition, $uniqueName)
    {
        $uniqueDefinition = $tableDefinition['uniques'][$uniqueName];
        $fields = array();
        foreach ($uniqueDefinition as $field) {
            if (isset($tableDefinition['fields'][$field]['name']) === true) {
                $fields[] = $tableDefinition['fields'][$field]['name'];
            } else {
                $fields[] = Helper::codifyName($modelName) . '_' . Helper::codifyName($field);
            }
        }
        $statement = $database->getLink()->prepare('ALTER TABLE ' . $tableName . ' ADD CONSTRAINT ' . $uniqueName . ' UNIQUE (' . implode(', ', $fields) . ')');

        return $statement;
    }
}
