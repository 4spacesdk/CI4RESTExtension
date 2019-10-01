<?php namespace RestExtension\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use CodeIgniter\Config\Config;
use Config\RestExtension;
use OrmExtension\ModelParser\ModelParser;

/**
 * Class ModelExporter
 * @package RestExtension\Commands
 */
class ModelExporter extends BaseCommand {

    public $group           = 'RestExtension';
    public $name            = 'model:export';
    public $description     = 'Translates PHP Models to TypeScript models';
    protected $usage        = 'model:export';
    protected $arguments    = [

    ];
    protected $options      = [

    ];

    /**
     * Actually execute a command.
     * This has to be over-ridden in any concrete implementation.
     *
     * @param array $params
     * @throws \ReflectionException
     */
    public function run(array $params) {
        $parser = ModelParser::run();
        $parser->generateTypeScript();

        /** @var RestExtension $config */
        $config = Config::get('RestExtension');

        $from = WRITEPATH . 'tmp/models';
        $from = str_replace(' ', '\ ', $from);
        $to = $config->typescriptModelExporterDestination;
        $to = str_replace(' ', '\ ', $to);

        // Create destination direction if not already exists
        if(!is_dir($to)) mkdir($to, 0777, true);

        // Clear Definition folder
        shell_exec("rm -rf {$to}/definitions");
        shell_exec("mv {$from}/definitions {$to}");

        // Overwrite index file
        shell_exec("rm -rf {$to}/index.ts");
        shell_exec("mv {$from}/index.ts {$to}");

        // Add new models to model list
        shell_exec("cp -n {$from}/*.ts {$to}/");

        // Cleanup
        shell_exec("rm -rf {$from}");
    }

}
