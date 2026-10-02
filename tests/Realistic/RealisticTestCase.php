<?php

namespace Tests\Realistic;

use CodeIgniter\Test\CIUnitTestCase;
use Tests\Support\Ask;
use Tests\Support\Crm\CrmClient;
use Tests\Support\Crm\CrmWorld;
use Tests\Support\Engine;

/**
 * The CRM, loaded once per database (CrmWorld), asked as an admin and as three reps.
 */
abstract class RealisticTestCase extends CIUnitTestCase
{
    protected const USERS = ['admin', 'manager', 'member', 'loner'];

    /** @var array<string, int> */
    private static array $reps = [];

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        \OrmExtension\Hooks\PreController::execute();
        \OrmExtension\DataMapper\ModelDefinitionCache::getInstance()->clearCache();
        CrmWorld::load();
        self::$reps = CrmWorld::reps();
    }

    protected function setUp(): void
    {
        parent::setUp();
        Engine::reset();
        CrmClient::admin();
    }

    protected function tearDown(): void
    {
        Engine::reset();
        CrmClient::admin();
        parent::tearDown();
    }

    protected static function signIn(string $user): void
    {
        if ($user === 'admin') {
            CrmClient::admin();

            return;
        }
        self::assertArrayHasKey($user, self::$reps);
        self::assertGreaterThan(0, self::$reps[$user], "no {$user} in the CRM");
        CrmClient::signInAs(self::$reps[$user]);
    }

    /**
     * @return class-string
     */
    protected static function modelClass(string $model): string
    {
        return 'Tests\Support\Crm\Models\\' . str_replace(' ', '', ucwords(str_replace('_', ' ', $model))) . 'Model';
    }

    /**
     * The ids of one page, by id.
     *
     * @return list<int>|string
     */
    protected static function page(string $model, string $filter, int $limit, int $offset): array|string
    {
        $rows = Ask::rows(self::modelClass($model), $filter, 'id:asc', null, $limit, $offset);

        return is_array($rows) ? array_map(static fn (array $row): int => (int) $row['id'], $rows) : $rows;
    }
}
