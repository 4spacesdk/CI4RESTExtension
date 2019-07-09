<?php namespace RestExtension\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Class ControllerGenerator
 * @package RestExtension\Commands
 */
class ControllerGenerator extends BaseCommand {

    public $group           = 'RestExtension';
    public $name            = 'controller:create';
    public $description     = 'Generate new Controller with ApiRoutes added to latest migration file';
    protected $usage        = 'controller:create [ControllerName] [Options]';
    protected $arguments    = [
        'ModelName' => 'The Controller Class Name, ex. Users'
    ];
    protected $options      = [
        '-skip_migration' => 'Skip adding ApiRoutes to Migration file'
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
        $this->createControllerFile($name);

        if(!$skipMigration)
            $this->addMigrationRoutes($name);
    }

    private function createControllerFile($name) {
        $template = <<<EOD
<?php namespace App\Controllers;

use App\Core\ResourceController;

class {$name} extends ResourceController {

}
EOD;

        $fileName = "{$name}.php";
        $path = APPPATH."/Controllers/{$fileName}";
        if(!write_file($path, $template)) {
            CLI::error('Error trying to create file.');
            return;
        }
        shell_exec("git add {$path}");

        CLI::write('Created file: ' . CLI::color($fileName, 'green'));
    }

    private function addMigrationRoutes($name) {
        $path = $this->getLastModifiedMigrationFile();
        $file = file_get_contents($path);

        $template = <<<EOD
    \RestExtension\Entities\ApiRoute::addResourceController(\App\Controllers\\{$name}::class);
            
    }
EOD;

        $file = substr_replace($file, $template, strpos($file, '}'), 1);

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
