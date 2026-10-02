<?php

namespace Tests\Differential;

use CodeIgniter\Events\Events;
use Tests\Support\Ask;
use Tests\Support\Engine;
use Tests\Support\Models\OrderModel;
use Tests\Support\Models\WorkspaceModel;

/**
 * How the rules engine asks: the keys of a narrow filter written into the query, a broad one
 * left as a sub query, and an include fetched for all rows at once.
 */
final class EngineTest extends DifferentialTestCase
{
    private static int $queries = 0;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        Events::on('DBQuery', static function (): void {
            self::$queries++;
        });
    }

    public function testAFewKeysAreWrittenIntoTheQuery(): void
    {
        Engine::$candidate = true;
        Engine::$keyListLimit = 1000;

        $this->assertSame([1, 4], Ask::ids(OrderModel::class, 'buyer_workspace.name:Alpha'));
        $this->assertStringContainsString("`orders`.`buyer_workspace_id` IN ('1')", self::lastQuery());
    }

    public function testManyStayASubQuery(): void
    {
        Engine::$candidate = true;
        Engine::$keyListLimit = 0;

        $this->assertSame([1, 4], Ask::ids(OrderModel::class, 'buyer_workspace.name:Alpha'));
        $this->assertStringContainsString('`orders`.`buyer_workspace_id` IN (SELECT', self::lastQuery());
    }

    public function testAHasManyIncludeIsOneRequestForAllRows(): void
    {
        self::$queries = 0;
        Ask::rows(WorkspaceModel::class, '', 'id:asc', 'user');
        $reference = self::$queries;

        Engine::$candidate = true;
        self::$queries = 0;
        Ask::rows(WorkspaceModel::class, '', 'id:asc', 'user');

        // The workspaces, which users belong to which, and the users - against one request per
        // workspace
        $this->assertSame(3, self::$queries);
        $this->assertGreaterThan(5, $reference);
    }

    private static function lastQuery(): string
    {
        return (string) db_connect('tests')->getLastQuery();
    }
}
