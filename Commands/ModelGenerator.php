<?php namespace RestExtension\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Class ModelGenerator
 * @package RestExtension\Commands
 */
class ModelGenerator extends BaseCommand {

    public $group           = 'RestExtension';
    public $name            = 'model:create';
    public $description     = 'Generate new Model with Entity and Table added to latest migration file';
    protected $usage        = 'model:create [ModelName] [Options]';
    protected $arguments    = [
        'ModelName' => 'The Model Class Name, ex. User'
    ];
    protected $options      = [
        '-skip_migration' => 'Skip adding Table to Migration file'
    ];

    /**
     * Actually execute a command.
     * This has to be over-ridden in any concrete implementation.
     *
     * @param array $params
     */
    public function run(array $params) {
        $name = array_shift($params);

        if(empty($name)) {
            $name = CLI::prompt('Name the model');
        }

        if(empty($name)) {
            CLI::error('You must provide a Model name.');
            return;
        }

        $skipMigration = CLI::getOption('skip_migration');

        helper('filesystem');
        $this->createModelFile($name);
        $this->createEntityFile($name);

        if(!$skipMigration)
            $this->addMigrationTable(plural(strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $name)))); // Camel to snake
    }

    private function createModelFile($name) {
        $template = <<<EOD
<?php namespace App\Models;

use RestExtension\Core\Model;
use RestExtension\QueryParser;
use RestExtension\ResourceModelInterface;
use App\Entities\\{$name};

/**
 * Class {$name}Model
 * @package App\Models
 */
class {$name}Model extends Model implements ResourceModelInterface {
    
    public \$hasOne = [
    
    ];

    public \$hasMany = [
        
    ];

    /**
     * @param QueryParser \$queryParser
     * @param \$id
     */
    public function preRestGet(\$queryParser, \$id) {

    }

    /**
     * @param QueryParser \$queryParser
     * @param {$name} \$items
     */
    public function postRestGet(\$queryParser, \$items) {

    }

    /**
     * @param {$name} \$item
     * @return boolean
     */
    public function isRestCreationAllowed(\$item): bool {
        return true;
    }

    /**
     * @param {$name} \$item
     * @return boolean
     */
    public function isRestUpdateAllowed(\$item): bool {
        return true;
    }

    /**
     * @param {$name} \$item
     * @return boolean
     */
    public function isRestDeleteAllowed(\$item): bool {
        return true;
    }

    /**
     * @param {$name} \$items
     */
    public function appleRestGetManyRelations(\$items) {

    }
}
EOD;

        $fileName = "{$name}Model.php";
        $path = APPPATH."/Models/{$fileName}";
        if(!write_file($path, $template)) {
            CLI::error('Error trying to create file.');
            return;
        }
        shell_exec("git add {$path}");

        CLI::write('Created file: ' . CLI::color($fileName, 'green'));
    }

    private function createEntityFile($name) {
        $template = <<<EOD
<?php namespace App\Entities;

use RestExtension\Core\Entity;

/**
 * Class {$name}
 * @package App\Entities
 */
class {$name} extends Entity  {

}
EOD;

        $fileName = "{$name}.php";
        $path = APPPATH."/Entities/{$fileName}";
        if(!write_file($path, $template)) {
            CLI::error('Error trying to create file.');
            return;
        }
        shell_exec("git add {$path}");

        CLI::write('Created file: ' . CLI::color($fileName, 'green'));
    }

    private function addMigrationTable($table) {
        $path = $this->getLastModifiedMigrationFile();
        $file = file_get_contents($path);

        $template = <<<EOD
        
        Table::init('{$table}')
            ->create();
    }
EOD;
        $file = substr_replace($file, $template, strpos($file, '}'), 1);

        $template = <<<EOD
        
        Table::init('{$table}')
            ->dropTable();
    }
EOD;
        $file = substr_replace($file, $template, strpos($file, '}', strpos($file, '}') + 1), 1);
        file_put_contents($path, $file);
    }

    private function getLastModifiedMigrationFile() {
        $path = APPPATH . '/Database/Migrations';
        $files = glob("$path/*.php");
        $files = array_combine($files, array_map("filemtime", $files));
        arsort($files);
        return key($files);
    }

}
