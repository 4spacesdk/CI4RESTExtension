<?php namespace RestExtension\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\RestExtension;
use OrmExtension\ModelParser\ModelParser;
use RestExtension\ApiParser\ApiParser;

/**
 * Class ApiExporter
 * @package RestExtension\Commands
 */
class ApiExporter extends BaseCommand {

    public $group           = 'RestExtension';
    public $name            = 'api:export';
    public $description     = 'Translates Rest API to TypeScript API Structure';
    protected $usage        = 'api:export';
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
        $parser = ApiParser::run();
        $parser->generateTypeScript(false);

        /** @var RestExtension $config */
        $config = config('RestExtension');

        $from = WRITEPATH . 'tmp/Api.ts';
        $from = str_replace(' ', '\ ', $from);
        $to = $config->typescriptAPIExporterDestination;
        $to = str_replace(' ', '\ ', $to);

        // Create destination direction if not already exists
        clearstatcache();
        if(!is_dir($to)) {
            mkdir($to, 0777, true);
        }

        // Overwrite Api.ts
        shell_exec("rm -rf {$to}/Api.ts");
        shell_exec("mv {$from} {$to}/Api.ts");
    }

}
