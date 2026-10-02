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

    /**
     * RestExtension includes buyer_workspace.user as nothing on the order and `users` of the
     * wrong workspace; the rules engine includes the buyer, and the buyer's users.
     */
    public function testAPathThroughAHasManyIsIncludedAtEveryLevel(): void
    {
        Engine::$candidate = true;

        $orders = Ask::rows(OrderModel::class, '', 'id:asc', 'buyer_workspace.user');
        $workspaces = Ask::rows(WorkspaceModel::class, '', 'id:asc', 'user');
        $this->assertIsArray($orders);
        $this->assertIsArray($workspaces);
        $users = [];
        foreach ($workspaces as $workspace) {
            $users[$workspace['id']] = array_column($workspace['users'] ?? [], 'id');
        }
        foreach ($orders as $order) {
            $buyer = $order['buyer_workspace']['id'] ?? null;
            $this->assertSame($buyer === null ? [] : $users[$buyer], array_column($order['buyer_workspace']['users'] ?? [], 'id'), "order {$order['id']}");
            $this->assertArrayNotHasKey('users', $order);
        }

        $lines = Ask::rows(OrderModel::class, 'id:1', 'id:asc', 'order_line.product');
        $this->assertIsArray($lines);
        $this->assertSame(['Apple', 'Banana'], array_map(static fn (array $line): ?string => $line['product']['name'] ?? null, $lines[0]['order_lines']));
    }

    private static function lastQuery(): string
    {
        return (string) db_connect('tests')->getLastQuery();
    }
}
