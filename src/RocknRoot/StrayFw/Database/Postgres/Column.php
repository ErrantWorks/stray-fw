<?php

namespace RocknRoot\StrayFw\Database\Postgres;

use RocknRoot\StrayFw\Exception\InvalidSchemaDefinition;

/**
 * Column helper functions.
 *
 * @abstract
 *
 * @author Nekith <nekith@errant-works.com>
 */
abstract class Column
{
    /**
     * Generate a column SQL definition.
     *
     * @throws InvalidSchemaDefinition if default value aren't well typed
     * @throws InvalidSchemaDefinition if a field has an unknown type
     * @param  array                   $schema          schema definition
     * @param  string                  $mapping         mapping name
     * @param  string                  $fieldAlias      field alias name
     * @param  string                  $fieldName       field real name
     * @param  array                   $fieldDefinition field definition
     */
    public static function generateDefinition(array $schema, $mapping, $fieldAlias, $fieldName, array $fieldDefinition)
    {
        $sql = $fieldName . ' ';
        switch ($definition['type']) {
        case 'bool':
            $sql .= 'BOOL';
            if (isset($definition['default']) === true) {
                if (is_bool($definition['default']) === false) {
                    throw new InvalidSchemaDefinition('default value for "' . $fieldName . '" isn\'t a boolean');
                }
                $sql .= ' DEFAULT ' . ($definition['default'] === true ? 'TRUE' : 'FALSE');
            }
            break;

        case 'char':
            $sql .= 'CHAR(' . (isset($definition['size']) === true ? $definition['size'] : 45) . ')';
            if (isset($definition['default']) === true) {
                $sql .= ' DEFAULT \'' . $definition['default'] . '\'';
            }
            break;

        case 'string':
            $sql .= 'VARCHAR(' . (isset($definition['size']) === true ? $definition['size'] : 45) . ')';
            if (isset($definition['default']) === true) {
                $sql .= ' DEFAULT \'' . $definition['default'] . '\'';
            }
            break;

        case 'serial':
            $sql .= 'SERIAL';
            break;

        case 'bigserial':
            $sql .= 'BIGSERIAL';
            break;

        case 'int':
            $sql .= 'INT';
            if (isset($definition['default']) === true) {
                $sql .= ' DEFAULT \'' . $definition['default'] . '\'';
            }
            break;

        case 'smallint':
            $sql .= 'SMALLINT';
            if (isset($definition['default']) === true) {
                $sql .= ' DEFAULT \'' . $definition['default'] . '\'';
            }
            break;

        case 'float':
            $sql .= 'FLOAT';
            if (isset($definition['default']) === true) {
                $sql .= ' DEFAULT \'' . $definition['default'] . '\'';
            }
            break;

        case 'timestamp':
            $sql .= 'TIMESTAMP';
            if (isset($definition['default']) === true) {
                if ($definition['default'] == 'now') {
                    $sql .= ' DEFAULT CURRENT_TIMESTAMP';
                } else {
                    $sql .= ' DEFAULT \'' . $definition['default'] . '\'';
                }
            }
            break;

        case 'text':
            $sql .= 'TEXT';
            break;

        case 'json':
            $sql .= 'JSON';
            break;

        case 'blob':
            $sql .= 'BLOB';
            break;

        case 'bit':
            $sql .= 'BIT';
            if (isset($definition['size']) === true) {
                $sql .= '(' . $definition['size'] . ')';
            }
            break;

        case 'bitstring':
            $sql .= 'BIT VARYING';
            if (isset($definition['size']) === true) {
                $sql .= '(' . $definition['size'] . ')';
            }
            break;

        default:
            if (isset($schema[$fieldName]) === true) {
                if (isset($schema[$fieldName]['type']) === true) {
                    if ($schema[$fieldName]['type'] == 'enum') {
                        $enumRealName = null;
                        if (isset($schema[$fieldName]['name']) === true) {
                            $enumRealName = $schema[$fieldName]['name'];
                        } else {
                            $enumRealName = Helper::codifyName($mapping) . '_' . Helper::codifyName($fieldAlias);
                        }
                        $sql .= $enumRealName;
                        break;
                    }
                }
            }
            throw new InvalidSchemaDefinition('field "' . $fieldName . '" has an unknown type');
            break;
        }
        if (isset($definition['notnull']) === false || $definition['notnull'] === true) {
            $sql .= ' NOT NULL';
        }

        return $sql;
    }
}
