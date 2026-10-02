<?php

namespace Tests\Differential;

use Tests\Support\Ask;
use Tests\Support\Models\GroupModel;
use Tests\Support\Models\OrderModel;
use Tests\Support\Models\ProductModel;
use Tests\Support\Models\UserModel;
use Tests\Support\Models\WorkspaceModel;
use Tests\Support\TestClient;

/**
 * RestExtension as it is reads the test world as intended: the relations are wired, so the
 * other tests compare something.
 */
final class FixtureTest extends DifferentialTestCase
{
    public function testNamedRelationsToTheSameModel(): void
    {
        $this->assertSame([1, 4], Ask::ids(OrderModel::class, 'buyer_workspace.name:Alpha'));
        $this->assertSame([1, 5, 9], Ask::ids(OrderModel::class, 'seller_workspace.name:Beta'));
    }

    public function testCrossedNamesStillPairUp(): void
    {
        // The address an order ships to is that address's invoice_order
        // Through the address: orders shipped where A-1 was (Aarhus), and invoiced where A-1 was (Malmo)
        $this->assertSame([1, 5], Ask::ids(OrderModel::class, 'shipping_address.invoice_order.reference:A-1'));
        $this->assertSame([1, 2], Ask::ids(OrderModel::class, 'invoice_address.shipping_order.reference:A-1'));
    }

    public function testATreeBothWays(): void
    {
        $this->assertSame([4], Ask::ids(GroupModel::class, 'parent.name:Denmark'));
        $this->assertSame([2], Ask::ids(GroupModel::class, 'child.name:Jutland'));
    }

    public function testManyToManyThroughAPivotThatIsAlsoAnEntity(): void
    {
        $this->assertSame([1, 3], Ask::ids(WorkspaceModel::class, 'user.email:ann@a.dk'));
        $this->assertSame([3], Ask::ids(WorkspaceModel::class, 'users_workspace.status:pending'));
    }

    public function testANonIdJoin(): void
    {
        $this->assertSame([1, 2], Ask::ids(UserModel::class, 'favorite.product_no:P-100'));
        $this->assertSame([1, 2], Ask::ids(ProductModel::class, 'favorite_product.user.email:ann@a.dk'));
    }

    public function testTheRulesNarrowTheBaseRows(): void
    {
        TestClient::signInAs(2);

        $this->assertSame([1, 2], Ask::ids(WorkspaceModel::class));
        $this->assertSame([1, 2, 4, 5, 9], Ask::ids(OrderModel::class));
        $this->assertSame([1, 2], Ask::ids(ProductModel::class));
    }

    public function testComputedFieldsComeAlong(): void
    {
        $alpha = Ask::rows(WorkspaceModel::class, 'id:1', 'id:asc', 'count_users');

        $this->assertIsArray($alpha);
        // Approved pivot rows: Eve's counts, though Eve is deleted - the count looks at the pivot
        $this->assertSame(4, (int) $alpha[0]['count_users']);
    }
}
