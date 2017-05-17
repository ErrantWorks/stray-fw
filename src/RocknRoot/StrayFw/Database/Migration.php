<?php

namespace RocknRoot\StrayFw\Database;

use RocknRoot\StrayFw\Config;
use RocknRoot\StrayFw\Console\Request;
use RocknRoot\StrayFw\Exception\FileNotReadable;
use RocknRoot\StrayFw\Exception\FileNotWritable;

/**
 * Console actions for migration related operations.
 *
 * @author Nekith <nekith@errant-works.com>
 */
class Migration
{
    /**
     * Create a new migration.
     *
     * @param Request $request current CLI request
     * @throws FileNotReadable if can't find schema file
     * @throws FileNotWritable if can't copy schema file
     */
    public function create(Request $req)
    {
        if (count($req->getArgs()) != 2) {
            echo 'Wrong arguments.' . PHP_EOL . 'Usage : db/migration/create mapping_name migration_name' . PHP_EOL;
        } else {
            $mappingName = $req->getArgs()[0];
            $mapping = Mapping::get($mappingName);
            $name = ucfirst($req->getArgs()[1]);
            if ($this->write($mapping, $mappingName, $name) === true) {
                $path = rtrim($mapping['config']['migrations']['path'], DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
                $path .= $name . DIRECTORY_SEPARATOR . 'schema.yml';
                if (file_exists($mapping['config']['schema']) === false) {
                    throw new FileNotReadable('can\'t find "' . $mapping['schema'] . '"');
                }
                if (copy($mapping['config']['schema'], $path) === false) {
                    throw new FileNotWritable('can\'t copy "' . $mapping['schema'] . '" to "' . $path . '"');
                }
                $migrations = [];
                $path = rtrim($mapping['config']['migrations']['path'], DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'migrations.yml';
                if (file_exists($path) === true) {
                    $migrations = Config::get($path);
                }
                $migrations[] = [
                    'name' => $name,
                    'timestamp' => time(),
                ];
                Config::set($path, $migrations);
                echo 'Migration "' . $name . '" created.' . PHP_EOL;
            }
        }
    }

    /**
     * Generate code for migration.
     *
     * @param Request $request current CLI request
     * @throws FileNotReadable if can't find migrate
     */
    public function generate(Request $req)
    {
        if (count($req->getArgs()) != 2) {
            echo 'Wrong arguments.' . PHP_EOL . 'Usage : db/migration/create mapping_name migration_name' . PHP_EOL;
        } else {
            $mappingName = $req->getArgs()[0];
            $mapping = Mapping::get($mappingName);
            $name = ucfirst($req->getArgs()[1]);
            $path = rtrim($mapping['config']['migrations']['path'], DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
            $path .= $name . DIRECTORY_SEPARATOR . $name . '.php';
            if (file_exists($path) === false) {
                throw new FileNotReadable('can\'t find migration at "' . $path . '"');
            }
            $cl = ltrim(rtrim($mapping['config']['provider'], '\\'), '\\') . '\\Migration::generate';
            $res = call_user_func($cl, $mapping, $mappingName, $name);
            $this->write($mapping, $mappingName, $name, $res['up'], $res['down'], $res['import']);
            echo 'Migration "' . $name . '" generated.' . PHP_EOL;
            echo 'This is an automatic generation, please validate or rewrite parts of the migration.' . PHP_EOL;
            echo 'File is there:' . PHP_EOL;
            echo $path . PHP_EOL;
        }
    }

    public function migrate(Request $req)
    {
        echo 'Not implemented yet.' . PHP_EOL;
    }

    public function rollback(Request $req)
    {
        echo 'Not implemented yet.' . PHP_EOL;
    }

    private function write(array $mapping, string $mappingName, string $name, array $up = [], array $down = [], array $import = [])
    {
        $path = rtrim($mapping['config']['migrations']['path'], DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $path .= $name . DIRECTORY_SEPARATOR;
        if (file_exists($path . $name . '.php') === true) {
            echo 'A migration with this name already exists. Do you want to overwrite it ? [y/n] : ';
            if (fgetc(STDIN) != 'y') {
                return false;
            }
        }
        if (is_dir($path) === false) {
            if (mkdir($path) === false) {
                throw new FileNotWritable('can\'t mkdir "' . $path . '"');
            }
        }
        $path .= $name . '.php';
        $file = fopen($path, 'w+');
        if ($file === false) {
            throw new FileNotWritable('can\'t open "' . $path . '" with write permission');
        }
        $content = "<?php\n\nnamespace " . ltrim(rtrim($mapping['config']['migrations']['namespace'], '\\'), '\\') . '\\' . $name . ";\n\nuse " . ltrim(rtrim($mapping['config']['provider'], '\\'), '\\') . '\\Migration;' . PHP_EOL;
        $content .= 'use RocknRoot\StrayFw\Database\Database;' . PHP_EOL;
        $content .= 'use RocknRoot\StrayFw\Database\Mapping;' . PHP_EOL;
        foreach ($import as $imp) {
            $content .= 'use ' . ltrim(rtrim($mapping['config']['provider'], '\\'), '\\') . '\\Mutation\\' . $imp . ";\n";
        }
        $up = implode('', array_map(function(string $a) {
            return '        ' . $a;
        }, $up);
        $down = implode('', array_map(function(string $a) {
            return '        ' . $a;
        }, $down);
        $up = '    $mapping = Mapping::get(\'' . $mappingName . '\');' . PHP_EOL . $up;
        $up = '    $database = Database::get($mapping[\'config\'][\'database\']);\n' . $up;
        $up = '    $schema = Config::get($mapping[\'config\'][\'schema\']);\n' . $up;
        $down = '    $mapping = Mapping::get(\'' . $mappingName . '\');' . PHP_EOL . $down;
        $down = '    $database = Database::get($mapping[\'config\'][\'database\']);\n' . $down;
        $down = '    $schema = Config::get(__DIR__ . \'/schema.yml\');' . PHP_EOL . $down;
        $content .= "\nclass " . $name . " extends Migration\n{\n";
        $content .= '    const NAME = \'' . $name . "';\n    const MAPPING = '" . $mappingName . "';\n\n";
        $content .= "    public function up()\n    {\n" . $up . "    }\n\n";
        $content .= "    public function down()\n    {\n" . $down . "    }\n";
        $content .= "}";
        if (fwrite($file, $content) === false) {
            throw new FileNotWritable('can\'t write in "' . $path . '"');
        }
        fclose($file);

        return true;
    }
}
