<?php namespace RestExtension\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use CodeIgniter\Config\Config;
use Config\RestExtension;
use OrmExtension\ModelParser\ModelParser;
use RestExtension\ApiParser\ApiParser;

/**
 * Class UREMEA
 * @package RestExtension\Commands
 */
class UREMEA extends BaseCommand {

    public $group           = 'RestExtension';
    public $name            = 'deliverit:uremea';
    public $description     = 'UREMEA';
    protected $usage        = 'deliverit:UREMEA';
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
        // Migrate: Refresh this project
        $this->call('migrate:refresh');

        // Export Models
        $this->call('model:export');

        // Export API
        $this->call('api:export');
    }

}
