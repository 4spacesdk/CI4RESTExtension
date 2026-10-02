<?php

namespace Tests\Support\Models;

use RestExtension\Core\Model;
use RestExtension\ResourceModelInterface;
use Tests\Support\FollowsEngine;
use Tests\Support\RulelessModel;
use Tests\Support\TestClient;

/**
 * Two named relations each way to orders, crossed as in order-service's AddressModel:
 * invoice_order is the order's shipping_address. Its rule is sloppy on purpose - an orWhere
 * outside a group - as rules sometimes are.
 */
class AddressModel extends Model implements ResourceModelInterface
{
    use FollowsEngine;
    use RulelessModel { preRestGet as private noRules; }

    public $hasMany = [
        WorkspaceModel::class,
        'invoice_workspace' => ['class' => WorkspaceModel::class, 'otherField' => 'invoice_address', 'joinTable' => 'workspaces', 'joinSelfAs' => 'invoice_address_id', 'joinOtherAs' => 'id'],
        'invoice_order'  => ['class' => OrderModel::class, 'otherField' => 'shipping_address', 'joinTable' => 'orders', 'joinSelfAs' => 'shipping_address_id', 'joinOtherAs' => 'id'],
        'shipping_order' => ['class' => OrderModel::class, 'otherField' => 'invoice_address', 'joinTable' => 'orders', 'joinSelfAs' => 'invoice_address_id', 'joinOtherAs' => 'id'],
    ];

    public function preRestGet($queryParser, $id)
    {
        if (! TestClient::$admin) {
            $this->where('city !=', 'Hidden')->orWhere('country_code', 'DK');
        }
    }
}
