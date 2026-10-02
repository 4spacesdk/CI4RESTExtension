<?php

namespace Tests\Support\Models;

use RestExtension\Core\Model;
use RestExtension\ResourceModelInterface;
use Tests\Support\FollowsEngine;
use Tests\Support\RulelessModel;
use Tests\Support\TestClient;

/**
 * As user-service's WorkspaceModel: an address and a named invoice address, users many-to-many
 * through users_workspaces, which is also an entity, orders by two named relations, and a
 * computed count of approved members. A caller sees the workspaces they are approved in.
 */
class WorkspaceModel extends Model implements ResourceModelInterface
{
    use FollowsEngine;
    use RulelessModel { preRestGet as private noRules; }

    public $hasOne = [
        GroupModel::class,
        AddressModel::class,
        'invoice_address' => ['class' => AddressModel::class, 'otherField' => 'invoice_workspace', 'joinTable' => 'workspaces', 'joinSelfAs' => 'id', 'joinOtherAs' => 'invoice_address_id'],
    ];
    public $hasMany = [
        UserModel::class,
        UsersWorkspaceModel::class,
        'buyer_order'  => ['class' => OrderModel::class, 'otherField' => 'buyer_workspace', 'joinTable' => 'orders', 'joinSelfAs' => 'buyer_workspace_id', 'joinOtherAs' => 'id'],
        'seller_order' => ['class' => OrderModel::class, 'otherField' => 'seller_workspace', 'joinTable' => 'orders', 'joinSelfAs' => 'seller_workspace_id', 'joinOtherAs' => 'id'],
        ProductModel::class,
        'target_product' => ['class' => ProductModel::class, 'otherField' => 'target_workspace', 'joinTable' => 'products_target_workspaces', 'joinSelfAs' => 'workspace_id', 'joinOtherAs' => 'product_id'],
    ];

    public function preRestGet($queryParser, $id)
    {
        // As message-service's countParticipants: computed when asked for, as an include
        if ($queryParser->hasInclude('count_users')) {
            $queryParser->getInclude('count_users')->ignoreAuto = true;
            $members = (new UsersWorkspaceModel())->select('COUNT(*) as id', true, false)->where('status', 'approved')->where('workspace_id', '${parent}.id', false);
            $this->select('*')->selectSubQuery($members, 'count_users');
        }
        if (! TestClient::$admin) {
            $this->whereIn('id', TestClient::workspaceIds());
        }
    }
}
