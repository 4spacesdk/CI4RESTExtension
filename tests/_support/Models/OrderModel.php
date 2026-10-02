<?php

namespace Tests\Support\Models;

use RestExtension\Core\Model;
use RestExtension\ResourceModelInterface;
use Tests\Support\FollowsEngine;
use Tests\Support\RulelessModel;
use Tests\Support\TestClient;

/**
 * As order-service's OrderModel: buyer and seller workspace, shipping and invoice address, all
 * named relations to the same models; a parent order and its children; lines; and a computed
 * line count. A caller sees the orders their workspaces buy or sell in.
 */
class OrderModel extends Model implements ResourceModelInterface
{
    use FollowsEngine;
    use RulelessModel { preRestGet as private noRules; }

    public $hasOne = [
        'buyer_workspace'  => ['class' => WorkspaceModel::class, 'otherField' => 'buyer_order', 'joinTable' => 'orders', 'joinSelfAs' => 'id', 'joinOtherAs' => 'buyer_workspace_id'],
        'seller_workspace' => ['class' => WorkspaceModel::class, 'otherField' => 'seller_order', 'joinTable' => 'orders', 'joinSelfAs' => 'id', 'joinOtherAs' => 'seller_workspace_id'],
        'shipping_address' => ['class' => AddressModel::class, 'otherField' => 'invoice_order', 'joinTable' => 'orders', 'joinSelfAs' => 'id', 'joinOtherAs' => 'shipping_address_id'],
        'invoice_address'  => ['class' => AddressModel::class, 'otherField' => 'shipping_order', 'joinTable' => 'orders', 'joinSelfAs' => 'id', 'joinOtherAs' => 'invoice_address_id'],
        'parent' => ['class' => OrderModel::class, 'otherField' => 'child', 'joinSelfAs' => 'id', 'joinOtherAs' => 'parent_id'],
    ];
    public $hasMany = [
        OrderLineModel::class,
        'child' => ['class' => OrderModel::class, 'otherField' => 'parent', 'joinTable' => 'orders', 'joinSelfAs' => 'parent_id', 'joinOtherAs' => 'id'],
    ];

    public function preRestGet($queryParser, $id)
    {
        if ($queryParser->hasInclude('count_lines')) {
            $queryParser->getInclude('count_lines')->ignoreAuto = true;
            $lines = (new OrderLineModel())->select('COUNT(*) as id', true, false)->where('order_id', '${parent}.id', false);
            $this->select('*')->selectSubQuery($lines, 'count_lines');
        }
        if (! TestClient::$admin) {
            $workspaces = TestClient::workspaceIds();
            $this->groupStart()
                ->whereInRelated('buyer_workspace', 'id', $workspaces)
                ->orWhereInRelated('seller_workspace', 'id', $workspaces)
                ->groupEnd();
        }
    }
}
