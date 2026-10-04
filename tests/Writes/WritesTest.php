<?php

namespace Tests\Writes;

use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\SiteURI;
use CodeIgniter\HTTP\UserAgent;
use Config\App;
use DebugTool\Data;
use RestExtension\QueryParser;
use RestExtension\ResourceControllerTrait;
use Tests\Differential\DifferentialTestCase;
use Tests\Support\Engine;
use Tests\Support\Models\OrderModel;
use Tests\Support\TestClient;

/**
 * Writes through the resource controller, as Bo - approved in Alpha and Beta, so he may read the
 * orders those buy or sell (1, 2, 4, 5, 9) and not the others (3 Gamma/Delta, 8). With
 * writesFollowRules on a write goes through the same rules as a read; each case says what
 * RestExtension does with it off.
 */
final class WritesTest extends DifferentialTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        db_connect('tests')->transBegin();
        TestClient::signInAs(2);
        Engine::$writes = true;
    }

    protected function tearDown(): void
    {
        db_connect('tests')->transRollback();
        parent::tearDown();
    }

    public function testAnEmptyPatchOfARowHeMayNotReadIsNotThere(): void
    {
        // Off: 200 with all of G-3, without asking a rule - nothing changed, so nothing was saved
        $answer = self::send('patch', 3, []);

        $this->assertSame([404, 'ResourceNotFound'], [$answer['status'], $answer['error']]);
        $this->assertNull($answer['resource']);
    }

    public function testNorIsAPatchOrPutOrDeleteOfOne(): void
    {
        $this->assertSame(404, self::send('patch', 3, ['reference' => 'Mine'])['status']);
        $this->assertSame(404, self::send('put', 3, ['reference' => 'Mine'])['status']);
        // Off: 200, and G-3 is deleted
        $this->assertSame(404, self::send('delete', 3)['status']);
        $this->assertSame('G-3', self::reference(3));
    }

    public function testNorOneThatIsDeletedOrNotThereAtAll(): void
    {
        $this->assertSame(404, self::send('patch', 7, [])['status']);
        // Off: 200
        $this->assertSame(404, self::send('delete', 999)['status']);
    }

    public function testARowHeMayReadHeMayChange(): void
    {
        $answer = self::send('patch', 4, ['reference' => 'A-4b']);

        $this->assertSame(200, $answer['status']);
        $this->assertSame('A-4b', $answer['resource']['reference']);
        $this->assertSame('A-4b', self::reference(4));
    }

    public function testAPostCreatesWhateverIdItIsGiven(): void
    {
        // Off: 200 with all of G-3 and the body's reference, and nothing created
        $answer = self::send('post', null, ['id' => 3, 'reference' => 'Mine']);

        $this->assertSame(200, $answer['status']);
        $this->assertNotSame(3, (int) $answer['resource']['id']);
        $this->assertSame('Mine', self::reference((int) $answer['resource']['id']));
        $this->assertSame('G-3', self::reference(3));
    }

    public function testOneRowPerRequest(): void
    {
        // Off: 200 with all of G-3 in the list
        $answer = self::send('post', null, [['id' => 3], ['reference' => 'Two']]);

        $this->assertSame([400, 'OneResourcePerRequest'], [$answer['status'], $answer['error']]);
        $this->assertNull($answer['resources']);
    }

    public function testARelationGivenAsAnObjectIsNotWritten(): void
    {
        // Off: the order is Gamma's purchase, Gamma is renamed, and the answer holds all of Gamma
        $answer = self::send('post', null, ['reference' => 'Mine', 'buyer_workspace' => ['id' => 3, 'name' => 'Renamed']]);

        $this->assertSame(200, $answer['status']);
        $row = db_connect('tests')->table('orders')->where('id', (int) $answer['resource']['id'])->get()->getRowArray();
        $this->assertNull($row['buyer_workspace_id'] ?? null);
        $this->assertSame('Gamma', db_connect('tests')->table('workspaces')->where('id', 3)->get()->getRowArray()['name'] ?? null);
        $this->assertArrayNotHasKey('buyer_workspace', $answer['resource']);

        self::send('patch', 4, ['buyer_workspace' => ['id' => 3], 'order_lines' => [['id' => 4, 'quantity' => 99]]]);
        $this->assertSame('1', (string) db_connect('tests')->table('orders')->where('id', 4)->get()->getRowArray()['buyer_workspace_id']);
        $this->assertSame('1', (string) db_connect('tests')->table('order_lines')->where('order_id', 4)->get()->getRowArray()['quantity']);
    }

    public function testARelationIsWrittenByItsColumn(): void
    {
        $answer = self::send('post', null, ['reference' => 'Mine', 'buyer_workspace_id' => 2]);

        $this->assertSame(2, (int) db_connect('tests')->table('orders')->where('id', (int) $answer['resource']['id'])->get()->getRowArray()['buyer_workspace_id']);
    }

    public function testARulesNoIsForbidden(): void
    {
        TestClient::$mayWrite = false;

        // Off: 200 with the unsaved row, and the body's changes in it
        $this->assertSame([403, 'InsufficientAccess'], array_values(array_intersect_key(self::send('post', null, ['reference' => 'Mine']), ['status' => 1, 'error' => 1])));
        $this->assertSame(403, self::send('patch', 4, ['reference' => 'Mine'])['status']);
        $this->assertSame(403, self::send('put', 4, ['reference' => 'Mine'])['status']);
        $this->assertSame(403, self::send('delete', 4)['status']);
        $this->assertSame('A-4', self::reference(4));
    }

    public function testOffNothingChanges(): void
    {
        Engine::$writes = false;

        $answer = self::send('patch', 3, []);

        $this->assertSame(200, $answer['status']);
        $this->assertSame('G-3', $answer['resource']['reference']);
    }

    /**
     * @param array<mixed>|null $body
     *
     * @return array{status: int, error: string|null, resource: array<string, mixed>|null, resources: list<array<string, mixed>>|null}
     */
    private static function send(string $method, ?int $id, ?array $body = null): array
    {
        Data::del('resource');
        Data::del('resources');
        $controller = new OrdersController(self::request($body));
        match ($method) {
            'post'   => $controller->post(),
            'patch'  => $controller->patch($id),
            'put'    => $controller->put($id),
            'delete' => $controller->delete($id),
        };

        return [
            'status'    => $controller->status,
            'error'     => $controller->errorCode,
            'resource'  => Data::get('resource'),
            'resources' => Data::get('resources'),
        ];
    }

    /**
     * @param array<mixed>|null $body
     */
    private static function request(?array $body): IncomingRequest
    {
        $config = new App();

        return new IncomingRequest($config, new SiteURI($config, 'orders'), $body === null ? null : (string) json_encode($body), new UserAgent());
    }

    private static function reference(int $id): ?string
    {
        return db_connect('tests')->table('orders')->where('id', $id)->get()->getRowArray()['reference'] ?? null;
    }
}

/**
 * A resource controller as an app has one, answering into the envelope.
 */
final class OrdersController
{
    use ResourceControllerTrait;

    public int $status = 200;

    public ?string $errorCode = null;

    public function __construct(public IncomingRequest $request)
    {
        $this->resource = OrderModel::class;
        $this->queryParser = new QueryParser();
    }

    public function _setResource($item): void
    {
        Data::set('resource', $item->toArray());
    }

    public function _setResources($items): void
    {
        Data::set('resources', $items->allToArray());
    }

    public function error($errorCode, $statusCode = 503): void
    {
        $this->status = $statusCode;
        $this->errorCode = $errorCode;
    }

    public function success(): void
    {
    }
}
