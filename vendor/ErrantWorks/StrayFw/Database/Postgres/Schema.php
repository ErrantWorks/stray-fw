<?php

namespace ErrantWorks\StrayFw\Database\Postgres;

use ErrantWorks\StrayFw\Database\Helper;
use ErrantWorks\StrayFw\Database\Mapping;
use ErrantWorks\StrayFw\Database\Provider\Schema as ProviderSchema;
use ErrantWorks\StrayFw\Database\Database as GlobalDatabase;
use ErrantWorks\StrayFw\Exception\DatabaseError;
use ErrantWorks\StrayFw\Exception\FileNotWritable;
use ErrantWorks\StrayFw\Exception\InvalidSchemaDefinition;

/**
 * Schema representation class for PostgreSQL ones.
 * User code shouldn't use this class directly, nor the entire Postgres namespace.
 *
 * @author Nekith <nekith@errant-works.com>
 */
class Schema extends ProviderSchema
{
    /**
     * Build data structures.
     *
     * @throws DatabaseError           if a SQL query fails
     * @throws InvalidSchemaDefinition if a model has no field
     * @throws InvalidSchemaDefinition if an enum-typed field has no values defined
     */
    public function build()
    {
        $mapping = Mapping::get($this->mapping);
        $definition = $this->getDefinition();
        $database = GlobalDatabase::get($mapping['config']['database']);

        foreach ($definition as $modelName => $modelDefinition) {
            if (isset($modelDefinition['links']) === false) {
                continue;
            }
            $tableName = null;
            if (isset($modelDefinition['name']) === true) {
                $tableName = $modelDefinition['name'];
            } else {
                $tableName = Helper::codifyName($this->mapping) . '_' . Helper::codifyName($modelName);
            }
            $query = $database->getLink()->query('SELECT COUNT(*) as count FROM pg_class WHERE relname = \'' . $tableName . '\'');
            $result = $query->fetch(\PDO::FETCH_ASSOC);
            if ($result['count'] != 0) {
                foreach ($modelDefinition['links'] as $keyName => $keyDefinition) {
                    $statement = Mutation\DeleteForeignKey::statement($database, $tableName, $keyName);
                    if ($statement->execute() == false) {
                        throw new DatabaseError('db/build : ' . print_r($statement->errorInfo(), true));
                    }
                }
            }
        }

        foreach ($definition as $modelName => $modelDefinition) {
            $tableName = null;
            if (isset($modelDefinition['name']) === true) {
                $tableName = $modelDefinition['name'];
            } else {
                $tableName = Helper::codifyName($this->mapping) . '_' . Helper::codifyName($modelName);
            }

            $statement = Mutation\DeleteTable::statement($database, $tableName);
            if ($statement->execute() == false) {
                throw new DatabaseError('db/build : ' . print_r($statement->errorInfo(), true));
            }

            if (isset($modelDefinition['fields']) === false) {
                throw new InvalidSchemaDefinition('model "' . $modelName . '" has no field');
            }
            foreach ($modelDefinition['fields'] as $fieldName => $fieldDefinition) {
                if ($fieldDefinition['type'] == 'enum') {
                    if (isset($fieldDefinition['values']) === false) {
                        throw new InvalidSchemaDefinition('enum-typed field "' . $fieldName . '" of model "' . $modelName . '" has no values defined');
                    }
                    $fieldRealName = null;
                    if (isset($fieldDefinition['name']) === true) {
                        $fieldRealName = $fieldDefinition['name'];
                    } else {
                        $fieldRealName = Helper::codifyName($modelName) . '_' . Helper::codifyName($fieldName);
                    }
                    $statement = Mutation\DeleteEnum::statement($database, $fieldRealName);
                    if ($statement->execute() == false) {
                        throw new DatabaseError('db/build : ' . print_r($statement->errorInfo(), true));
                    }
                    $statement = Mutation\AddEnum::statement($database, $fieldRealName, $fieldDefinition['values']);
                    if ($statement->execute() == false) {
                        throw new DatabaseError('db/build : ' . print_r($statement->errorInfo(), true));
                    }
                }
            }

            $statement = Mutation\AddTable::statement($database, $tableName, $modelName, $modelDefinition);
            if ($statement->execute() == false) {
                throw new DatabaseError('db/build : ' . print_r($statement->errorInfo(), true));
            }

            if (isset($modelDefinition['indexes']) === true) {
                foreach ($modelDefinition['indexes'] as $indexName => $indexDefinition) {
                    $statement = Mutation\AddIndex::statement($database, $modelName, $tableName, $modelDefinition, $indexName);
                    if ($statement->execute() == false) {
                        throw new DatabaseError('db/build : ' . print_r($statement->errorInfo(), true));
                    }
                }
            }

            if (isset($modelDefinition['links']) === true) {
                foreach ($modelDefinition['links'] as $foreignName => $foreignDefinition) {
                    $foreignTableName = null;
                    if (isset($definition[$foreignDefinition['model']]['name']) === true) {
                        $foreignTableName = $definition[$foreignDefinition['model']]['name'];
                    } else {
                        $foreignTableName = Helper::codifyName($this->mapping) . '_' . Helper::codifyName($foreignDefinition['model']);
                    }
                    $statement = Mutation\AddForeignKey::statement($database, $definition, $modelName, $tableName, $foreignName, $foreignTableName);
                    if ($statement->execute() == false) {
                        throw new DatabaseError('db/build : ' . print_r($statement->errorInfo(), true));
                    }
                }
            }

            echo $modelName . ' - Done' . PHP_EOL;
        }
    }

    /**
     * Generate base models.
     *
     * @throws InvalidSchemaDefinition if a model has no field
     * @throws InvalidSchemaDefinition if an enum-typed field has no values defined
     * @throws InvalidSchemaDefinition if a model is linked to an unknown model
     * @throws InvalidSchemaDefinition if, while building a link, a model has an unknown needed field
     * @throws FileNotWritable         if base model file can't be opened with write permission
     * @throws FileNotWritable         if model file can't be opened with write permission
     */
    public function generateModels()
    {
        $definition = $this->getDefinition();
        foreach ($definition as $modelName => $modelDefinition) {
            $uses = array();
            $primary = array();
            $constructor = '    public function __construct(array $fetch = null)' . "\n    {\n        parent::__construct();\n";
            $constructorDefaults = '        if ($fetch == null) {' . PHP_EOL . '            $this->new = false;' . "\n        } else {\n" . '            $fetch = array();' . "\n        }\n";
            $properties = null;
            $accessors = null;
            $allFieldsRealNames = "    public static function getAllFieldsRealNames()\n    {\n        return array(";
            $allFieldsAliases = "    public static function getAllFieldsAliases()\n    {\n        return array(";

            $modelRealName = null;
            if (isset($modelDefinition['name']) === true) {
                $modelRealName = $modelDefinition['name'];
            } else {
                $modelRealName = Helper::codifyName($this->mapping) . '_' . Helper::codifyName($modelName);
            }

            if (isset($modelDefinition['fields']) === false) {
                throw new InvalidSchemaDefinition('model "' . $modelName . '" has no field');
            }
            foreach ($modelDefinition['fields'] as $fieldName => $fieldDefinition) {
                $fieldRealName = null;
                if (isset($fieldDefinition['name']) === true) {
                    $fieldRealName = $fieldDefinition['name'];
                } else {
                    $fieldRealName = Helper::codifyName($modelName) . '_' . Helper::codifyName($fieldName);
                }

                $properties .= '    protected $field' .  ucfirst($fieldName) . ";\n";
                $properties .= '    const FIELD_' . strtoupper(Helper::codifyName($fieldName)) . ' = \'' . $modelRealName . '.' . $fieldRealName . "';\n";
                if ($fieldDefinition['type'] == 'enum') {
                    if (isset($fieldDefinition['values']) === false) {
                        throw new InvalidSchemaDefinition('enum-typed field "' . $fieldName . '" of model "' . $modelName . '" has no values defined');
                    }
                    foreach ($fieldDefinition['values'] as $value) {
                        $properties .= '    const ' . strtoupper(Helper::codifyName($fieldName)) . '_' . strtoupper(Helper::codifyName($value)) . ' = \'' . $value . "';\n";
                    }
                }
                $properties .= PHP_EOL;

                $constructor .= '        $this->field' .  ucfirst($fieldName) . ' = [ \'name\' => \'' . $fieldRealName . '\', \'alias\' => \'' . $fieldName . "', 'value' => null ];\n";
                $constructorDefaults .= '        if (empty($fetch[\'' . $fieldRealName . "']) === false) {\n            ";
                $constructorDefaults .= '$this->set' . ucfirst($fieldName) . '($fetch[\'' . $fieldRealName . "']);\n        } else {\n            ";
                $constructorDefaults .= '$this->set' . ucfirst($fieldName) . '(';
                if (isset($fieldDefinition['default']) === true) {
                    $constructorDefaults .= '\'' . $fieldDefinition['default'] . '\'';
                } else {
                    $constructorDefaults .= 'null';
                }
                $constructorDefaults .= ");\n        }\n";

                if (isset($fieldDefinition['primary']) === true && $fieldDefinition['primary'] === true) {
                    $primary[] = $fieldName;
                }

                $accessors .= '    public function get' . ucfirst($fieldName) . "()\n    {\n        ";
                switch ($fieldDefinition['type']) {
                    case 'string':
                        $accessors .= 'return stripslashes($this->field' . ucfirst($fieldName) . '[\'value\']);';
                        break;
                    case 'char':
                        $accessors .= 'return stripslashes($this->field' . ucfirst($fieldName) . '[\'value\']);';
                        break;
                    case 'bool':
                        $accessors .= 'return filter_var($this->field' . ucfirst($fieldName) . '[\'value\'], FILTER_VALIDATE_BOOLEAN);';
                        break;
                    case 'json':
                        $accessors .= 'return json_decode($this->field' . ucfirst($fieldName) . '[\'value\'], true);';
                        break;
                    default:
                        $accessors .= 'return $this->field' . ucfirst($fieldName) . '[\'value\'];';
                        break;
                }
                $accessors .= "\n    }\n\n";

                $accessors .= '    public function set' . ucfirst($fieldName) . '($value)' . "\n    {\n        ";
                switch ($fieldDefinition['type']) {
                case 'enum':
                    $accessors .= 'if (in_array($value, array(\'' . implode('\', \'', $fieldDefinition['values']) . '\')) === false) {' . "\n            return false;\n        }";
                    $accessors .= '        $this->field' . ucfirst($fieldName) . '[\'value\'] = $value;';
                    break;
                case 'bool':
                    $accessors .= '$this->field' . ucfirst($fieldName) . '[\'value\'] = (bool) $value;';
                    break;
                case 'json':
                    $accessors .= '$this->field' . ucfirst($fieldName) . '[\'value\'] = json_encode($value);';
                    break;
                default:
                    $accessors .= '$this->field' . ucfirst($fieldName) . '[\'value\'] = $value;';
                    break;
                }
                $accessors .= PHP_EOL . '        $this->modified[\'' . $fieldName . '\'] = true;';
                $accessors .= "\n        return true;\n    }\n\n";

                $allFieldsRealNames .= '\'' . $modelRealName . '.' . $fieldRealName . '\', ';
                $allFieldsAliases .= '\'' . $fieldName . '\', ';
            }

            if (isset($modelDefinition['links']) === true) {
                foreach ($modelDefinition['links'] as $linkName => $linkDefinition) {
                    if (isset($definition[$linkDefinition['model']]) === false) {
                        throw new InvalidSchemaDefinition('unknown model for link "' . $linkName . '" of model "' . $modelName . '"');
                    }
                    if (in_array($linkDefinition['model'], $uses) === false) {
                        $uses[] = ucfirst($linkDefinition['model']);
                    }
                    $linkedModel = $definition[$linkDefinition['model']];
                    $accessors .= '    public function getLinked' . ucfirst($linkName) . "()\n    {\n        ";
                    $accessors .= 'return ' . ucfirst($linkDefinition['model']) . '::fetchEntity([ ';
                    $links = array();
                    foreach ($linkDefinition['fields'] as $from => $to) {
                        if (isset($modelDefinition['fields'][$from]) === false) {
                            throw new InvalidSchemaDefinition('building link : model "' . $modelName . '" has no field named "' . $from . '"');
                        }
                        if (isset($linkedModel['fields']) === false || isset($linkedModel['fields'][$to]) === false) {
                            throw new InvalidSchemaDefinition('building link : model "' . $linkDefinition['model'] . '" has no field named "' . $to . '"');
                        }
                        $links[] = ucfirst($linkDefinition['model']) . '::FIELD_' . Helper::codifyName($to) . ' => $this->get' . ucfirst($from) . '()';
                    }
                    $accessors .= implode(', ', $links) . " ]);\n    }\n\n";
                }
            }

            $allFieldsRealNames = substr($allFieldsRealNames, 0, -2) . ");\n    }\n\n";
            $allFieldsAliases = substr($allFieldsAliases, 0, -2) . ");\n    }\n\n";
            $constructor .= $constructorDefaults . "    }\n\n";

            $mapping = Mapping::get($this->mapping);

            $path = null;
            if ($mapping['config']['models']['path'][0] == DIRECTORY_SEPARATOR) {
                $path = ltrim($mapping['config']['models']['path'], DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
            } else {
                $path = rtrim($mapping['dir'], DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . ltrim($mapping['config']['models']['path'], DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
            }
            $path .= 'Base' . DIRECTORY_SEPARATOR . ucfirst($modelName) . '.php';
            $file = fopen($path, 'w+');
            if ($file === false) {
                throw new FileNotWritable('can\'t open "' . $path . '" with write permission');
            }
            $content = "<?php\n\nnamespace " . rtrim($mapping['config']['models']['namespace'], '\\') . "\\Base;\n\nuse ErrantWorks\StrayFw\Database\Postgres\Model;\n";
            foreach ($uses as $foreign) {
                $content .= 'use ' . rtrim($mapping['config']['models']['namespace'], '\\') . '\\' . $foreign . ";\n";
            }
            $content .= "\nclass " . ucfirst($modelName) . " extends Model\n{\n";
            $content .= '    const NAME = \'' . $modelRealName . "';\n    const DATABASE = '" . $mapping['config']['database'] . "';\n";
            $content .= $properties . $constructor . $accessors . $allFieldsRealNames . $allFieldsAliases;
            $content .= "    public static function getPrimary()\n    {\n        return array('" . implode('\', \'', $primary) . "');\n    }\n";
            $content .= "}";
            if (fwrite($file, $content) === false) {
                throw new FileNotWritable('can\'t write in "' . $path . '"');
            }
            fclose($file);

            if ($mapping['config']['models']['path'][0] == DIRECTORY_SEPARATOR) {
                $path = ltrim($mapping['config']['models']['path'], DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
            } else {
                $path = rtrim($mapping['dir'], DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . ltrim($mapping['config']['models']['path'], DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
            }
            $path .= ucfirst($modelName) . '.php';
            if (file_exists($path) === false) {
                $file = fopen($path, 'w+');
                if ($file === false) {
                    throw new FileNotWritable('can\'t open "' . $path . '" with write permission');
                }
                $content = "<?php\n\nnamespace " . rtrim($mapping['config']['models']['namespace'], '\\') . ";\n\nuse " . rtrim($mapping['config']['models']['namespace'], '\\') . "\\Base\\" . ucfirst($modelName) . " as BaseModel;\n\nclass " . ucfirst($modelName) . " extends BaseModel\n{\n}";
                if (fwrite($file, $content) === false) {
                    throw new FileNotWritable('can\'t write in "' . $path . '"');
                }
            }

            echo $modelName . ' - Done' . PHP_EOL;
        }
    }
}
