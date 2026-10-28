<?php

namespace Tests\Export;

use CodeIgniter\Config\BaseConfig;
use CodeIgniter\Config\Factories;
use CodeIgniter\Config\Services;
use CodeIgniter\Test\CIUnitTestCase;
use OrmExtension\ModelParser\ModelParser;
use RestExtension\ApiParser\ApiParser;
use Tests\Support\Api\First\ResourceController;

/**
 * The parsers say nothing PHP deprecates. CodeIgniter logs a deprecation with its stack trace, and
 * a property the parser items set without declaring it was one each time it was set: tens of
 * thousands of them for one document of an app, seconds to make it and a log of a gigabyte a day.
 */
final class DeprecationsTest extends CIUnitTestCase
{
    private const DOCUMENTED = 'Tests\Support\Api\Documented';

    private ?array $exportNamespace = null;

    /** @var list<string> */
    private array $deprecations = [];

    protected function setUp(): void
    {
        parent::setUp();
        helper('inflector');
        Services::autoloader()->addNamespace('Tests\Support\Api', dirname(__DIR__) . '/_support/Api');
        $config = new class () extends BaseConfig {
            public $apiControllerNamespace = 'Tests\Support\Api\Documented\Controllers';
            public $apiInterfaceNamespace = 'Tests\Support\Api\Documented\Interfaces';
            public $resourceControllerClass = ResourceController::class;
        };
        Factories::injectMock('config', 'RestExtension', $config);
        $this->exportNamespace = \Config\OrmExtension::$exportNamespace;
        \Config\OrmExtension::$exportNamespace = [self::DOCUMENTED . '\Entities'];
        set_error_handler(function (int $level, string $message, string $file, int $line): bool {
            $this->deprecations[] = "{$message} in {$file}:{$line}";

            return true;
        }, E_DEPRECATED | E_USER_DEPRECATED);
    }

    protected function tearDown(): void
    {
        restore_error_handler();
        Factories::reset('config');
        \Config\OrmExtension::$exportNamespace = $this->exportNamespace;
        parent::tearDown();
    }

    public function testTheApiParserSaysNothingDeprecated(): void
    {
        $parser = ApiParser::run();
        $parser->generateOpenApi(['info' => ['title' => 'Documented', 'version' => '1.0.0']]);
        $parser->generateSwagger();

        $this->assertSame([], array_values(array_unique($this->deprecations)));
    }

    public function testTheModelParserSaysNothingDeprecated(): void
    {
        $parser = ModelParser::run(true);
        $parser->generateOpenApi();
        $parser->generateSwagger();

        $this->assertSame([], array_values(array_unique($this->deprecations)));
    }
}
