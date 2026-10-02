<?php

namespace Tests\Differential;

use CodeIgniter\Test\CIUnitTestCase;
use Tests\Support\Engine;
use Tests\Support\Fixtures;
use Tests\Support\TestClient;

abstract class DifferentialTestCase extends CIUnitTestCase
{
    private static bool $loaded = false;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        // What an app's pre_system does for OrmExtension
        \OrmExtension\Hooks\PreController::execute();
        // OrmExtension keeps what it read about the models in a file cache; the test world changes
        \OrmExtension\DataMapper\ModelDefinitionCache::getInstance()->clearCache();
        if (! self::$loaded) {
            Fixtures::load(db_connect('tests'));
            self::$loaded = true;
        }
    }

    protected function setUp(): void
    {
        parent::setUp();
        Engine::reset();
    }

    protected function tearDown(): void
    {
        Engine::reset();
        TestClient::admin();
        parent::tearDown();
    }

    /**
     * The same request to both engines.
     *
     * @return array{mixed, mixed} reference, candidate
     */
    protected static function both(callable $request): array
    {
        Engine::$candidate = false;
        $reference = $request();
        Engine::$candidate = true;
        $candidate = $request();
        Engine::$candidate = false;

        return [$reference, $candidate];
    }
}
