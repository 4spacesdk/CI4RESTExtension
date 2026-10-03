<?php

namespace Tests\Export;

use CodeIgniter\Config\BaseConfig;
use CodeIgniter\Config\Factories;
use CodeIgniter\Config\Services;
use CodeIgniter\Test\CIUnitTestCase;
use OrmExtension\ModelParser\ModelParser;
use RestExtension\ApiParser\ApiParser;
use RestExtension\ApiParser\Sources;
use Tests\Support\Api\First\ResourceController;

/**
 * The API and model export reads controllers, interfaces and entities from every namespace it is
 * given - the app's and a package's - and, given one, exactly what it read before.
 */
final class ExportTest extends CIUnitTestCase
{
    private const FIRST = 'Tests\Support\Api\First';
    private const SECOND = 'Tests\Support\Api\Second';

    /** @var array<string, mixed> */
    private array $ormConfig = [];

    protected function setUp(): void
    {
        parent::setUp();
        helper('inflector');
        Services::autoloader()->addNamespace('Tests\Support\Api', dirname(__DIR__) . '/_support/Api');
        $config = new class () extends BaseConfig {
            public $apiControllerNamespace = ['Tests\Support\Api\First\Controllers', 'Tests\Support\Api\Second\Controllers'];
            public $apiInterfaceNamespace = ['Tests\Support\Api\First\Interfaces', 'Tests\Support\Api\Second\Interfaces'];
            public $resourceControllerClass = ['App\Core\ResourceController', ResourceController::class];
        };
        Factories::injectMock('config', 'RestExtension', $config);
        foreach (['exportNamespace', 'exportInterfaceNamespace'] as $property) {
            $this->ormConfig[$property] = property_exists(\Config\OrmExtension::class, $property) ? \Config\OrmExtension::$$property : null;
        }
    }

    protected function tearDown(): void
    {
        Factories::reset('config');
        foreach ($this->ormConfig as $property => $value) {
            if ($value !== null) {
                \Config\OrmExtension::$$property = $value;
            }
        }
        parent::tearDown();
    }

    public function testControllersOfEveryNamespaceAreFound(): void
    {
        $this->assertSame([
            self::FIRST . '\Controllers\Admin\Tools',
            self::FIRST . '\Controllers\Gadgets',
            self::SECOND . '\Controllers\Gizmos',
        ], self::sorted(Sources::controllers()));
    }

    public function testTheFirstNamespaceHasANameBothHave(): void
    {
        $this->assertNotContains(self::SECOND . '\Controllers\Gadgets', Sources::controllers());
        $this->assertSame(self::FIRST . '\Controllers\Gadgets', Sources::controllerClass('Gadgets'));
    }

    public function testATagIsTheNameWithoutItsNamespace(): void
    {
        $this->assertSame('AdminTools', Sources::relativeName(self::FIRST . '\Controllers\Admin\Tools'));
        $this->assertSame('Gizmos', Sources::relativeName(self::SECOND . '\Controllers\Gizmos'));
    }

    public function testTheParserExportsEveryEndpointAndInterface(): void
    {
        $parser = ApiParser::run();

        $routes = [];
        foreach ($parser->paths as $api) {
            foreach ($api->endpoints as $endpoint) {
                $routes[] = $endpoint->path;
            }
        }
        sort($routes);
        $this->assertSame(['/admin/tools', '/gadgets', '/gizmos'], $routes);
        $interfaces = array_map(static fn ($interface): string => $interface->name, $parser->interfaces);
        sort($interfaces);
        $this->assertSame(['GizmoItem', 'WidgetItem'], $interfaces);
    }

    public function testAnInterfaceIsFoundInAnyNamespace(): void
    {
        $this->assertSame(self::SECOND . '\Interfaces\GizmoItem', Sources::interfaceClass('GizmoItem'));
        $this->assertNull(Sources::interfaceClass('NoSuchItem'));
    }

    public function testAResourceControllerIsAChildOfAConfiguredBase(): void
    {
        $child = new \ReflectionClass(new class () extends ResourceController {});
        $other = new \ReflectionClass(new class () extends \CodeIgniter\Controller {});

        $this->assertTrue(Sources::isResourceController($child));
        $this->assertFalse(Sources::isResourceController($other));
    }

    public function testEntitiesOfEveryExportNamespaceAreModels(): void
    {
        \Config\OrmExtension::$exportNamespace = [self::FIRST . '\Entities', self::SECOND . '\Entities'];

        $names = array_map(static fn ($model): string => $model->name, ModelParser::run()->models);
        sort($names);

        $this->assertSame(['Gizmo', 'Widget'], $names);
    }

    /**
     * @param list<string> $classes
     *
     * @return list<string>
     */
    private static function sorted(array $classes): array
    {
        sort($classes);

        return $classes;
    }
}
