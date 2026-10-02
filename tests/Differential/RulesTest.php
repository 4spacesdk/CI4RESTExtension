<?php

namespace Tests\Differential;

use Tests\Support\Ask;
use Tests\Support\Engine;
use Tests\Support\Models\OrderModel;
use Tests\Support\Models\ProductModel;
use Tests\Support\Models\UserModel;
use Tests\Support\Models\WorkspaceModel;
use Tests\Support\TestClient;

/**
 * As Bo, approved in Alpha and Beta: a filter or include on a relation must not tell him about
 * a row of that relation he may not read. Each case says what the candidate must answer; the
 * comment says what RestExtension answers today, joined straight past the rule.
 */
final class RulesTest extends DifferentialTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        TestClient::signInAs(2);
        Engine::$candidate = true;
    }

    public function testAWorkspaceHeMayNotSeeIsNoFilter(): void
    {
        // Today [9]: order G-9 is sold by Beta to Gamma, and the filter says Gamma bought it
        $this->assertSame([], Ask::ids(OrderModel::class, 'buyer_workspace.name:Gamma'));
        $this->assertSame(0, Ask::count(OrderModel::class, 'buyer_workspace.name:Gamma'));
    }

    public function testNorTwoRelationsDeep(): void
    {
        // Today [9]: one of G-9's lines is Gamma's product Cherry, which Bo may not see
        $this->assertSame([], Ask::ids(OrderModel::class, 'order_line.product.name:Cherry'));
    }

    public function testNorThroughAManyToMany(): void
    {
        // Today [1]: Ann is a (pending) member of Gamma
        $this->assertSame([], Ask::ids(UserModel::class, 'workspace.name:Gamma'));
        // Today [2]: Banana targets Gamma
        $this->assertSame([], Ask::ids(ProductModel::class, 'target_workspace.name:Gamma'));
    }

    public function testNorThroughANonIdJoin(): void
    {
        // Today [1, 2]: it tells Bo that Ann has Apple as a favorite
        $this->assertSame([2], Ask::ids(UserModel::class, 'favorite.product_no:P-100'));
    }

    public function testWhatHeMaySeeStillFilters(): void
    {
        $this->assertSame([1], Ask::ids(WorkspaceModel::class, 'user.email:ann@a.dk'));
        $this->assertSame([1, 4], Ask::ids(OrderModel::class, 'buyer_workspace.name:Alpha'));
        $this->assertSame([2], Ask::ids(UserModel::class, 'favorite.product_no:P-300'));
    }

    public function testOneHeMayNotSeeIsNoRelation(): void
    {
        // Today [5]: D-5's buyer is deleted, and G-9's is Gamma, which has a name
        $this->assertSame([5, 9], Ask::ids(OrderModel::class, 'buyer_workspace.name:null'));
        $this->assertSame(2, Ask::count(OrderModel::class, 'buyer_workspace.name:null'));
    }

    public function testNorIsItAnEmptyOneAmongOthers(): void
    {
        // Bo is a pending member of Gamma too. Counted as a row with no name, Gamma would put
        // him in the answer, and tell him that a workspace he may not see holds him. Ann is in
        // it for a membership of a workspace that is not there at all.
        $this->assertSame([1], Ask::ids(UserModel::class, 'workspace.name:null'));
    }

    public function testAnOrderingSortsWhatHeMayNotSeeAsEmpty(): void
    {
        // Today [5, 1, 4, 2, 9]: G-9 last, because Gamma comes after Beta
        $this->assertSame([5, 9, 1, 4, 2], Ask::ids(OrderModel::class, '', null, 'buyer_workspace.name:asc,id:asc'));
    }

    public function testWhatHeMaySeeIsStillIncluded(): void
    {
        $orders = Ask::rows(OrderModel::class, 'id:[1,9]', 'id:asc', 'buyer_workspace,seller_workspace');

        $this->assertIsArray($orders);
        $this->assertSame('Alpha', $orders[0]['buyer_workspace']['name'] ?? null);
        $this->assertSame('Beta', $orders[1]['seller_workspace']['name'] ?? null);
    }

    /**
     * RestExtension fetches a has-many include through the related model already, one row at a
     * time; the rules engine does it for all rows at once, and must answer the same.
     */
    public function testAHasManyIncludeAnswersAsBefore(): void
    {
        $includes = [
            [WorkspaceModel::class, 'user'], [WorkspaceModel::class, 'users_workspace'], [WorkspaceModel::class, 'target_product'],
            [WorkspaceModel::class, 'buyer_order'], [WorkspaceModel::class, 'user?ordering=email:desc'], [UserModel::class, 'workspace'],
            [UserModel::class, 'favorite'], [OrderModel::class, 'order_line'], [ProductModel::class, 'target_workspace'],
        ];
        foreach ($includes as [$model, $include]) {
            [$reference, $candidate] = self::both(static fn () => Ask::rows($model, '', 'id:asc', $include));

            $this->assertSame(json_encode($reference), json_encode($candidate), "{$model} include={$include}");
        }
    }

    public function testAnIncludeHeMayNotSeeComesBackEmpty(): void
    {
        // Today the whole of Gamma comes along with G-9
        $orders = Ask::rows(OrderModel::class, 'id:9', 'id:asc', 'buyer_workspace');

        $this->assertIsArray($orders);
        $this->assertArrayNotHasKey('id', $orders[0]['buyer_workspace'] ?? []);
    }
}
