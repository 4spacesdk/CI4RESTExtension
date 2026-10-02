<?php

namespace Tests\Support\Models;

use RestExtension\Core\Model;
use RestExtension\ResourceModelInterface;
use Tests\Support\FollowsEngine;
use Tests\Support\RulelessModel;
use Tests\Support\TestClient;

/**
 * An order line, pointing at a product by product_no rather than its id. A caller sees the lines
 * of the orders they see, through two relations deep.
 */
class OrderLineModel extends Model implements ResourceModelInterface
{
    use FollowsEngine;
    use RulelessModel { preRestGet as private noRules; }

    public $hasOne = [
        OrderModel::class,
        'product' => ['class' => ProductModel::class, 'otherField' => 'order_line', 'joinTable' => 'products', 'joinSelfAs' => 'product_no', 'joinOtherAs' => 'product_no'],
    ];

    public function preRestGet($queryParser, $id)
    {
        if (! TestClient::$admin) {
            $workspaces = TestClient::workspaceIds();
            $this->groupStart()
                ->whereInRelated([OrderModel::class, 'buyer_workspace'], 'id', $workspaces)
                ->orWhereInRelated([OrderModel::class, 'seller_workspace'], 'id', $workspaces)
                ->groupEnd();
        }
    }
}
