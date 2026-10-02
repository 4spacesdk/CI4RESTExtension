<?php

namespace Tests\Support\Models;

use OrmExtension\Extensions\Entity;
use RestExtension\Core\Model;
use RestExtension\ResourceModelInterface;
use Tests\Support\FollowsEngine;
use Tests\Support\RulelessModel;
use Tests\Support\TestClient;

/**
 * As product-service's ProductModel: an owner workspace, target workspaces many-to-many through a
 * pivot of its own naming (with duplicates, as products_active_campaign_origins has), and
 * favorites and lines joined on product_no. A caller sees active products and their own; and
 * postRestGet() leaves out products without a name, in PHP.
 */
class ProductModel extends Model implements ResourceModelInterface
{
    use FollowsEngine;
    use RulelessModel { preRestGet as private noRules; postRestGet as private noPostRules; }

    public $hasOne = [
        WorkspaceModel::class,
    ];
    public $hasMany = [
        'target_workspace' => ['class' => WorkspaceModel::class, 'otherField' => 'target_product', 'joinTable' => 'products_target_workspaces', 'joinSelfAs' => 'product_id', 'joinOtherAs' => 'workspace_id'],
        'favorite_product' => ['class' => FavoriteModel::class, 'otherField' => 'product', 'joinTable' => 'products', 'joinSelfAs' => 'product_no', 'joinOtherAs' => 'product_no'],
        'order_line' => ['class' => OrderLineModel::class, 'otherField' => 'product', 'joinTable' => 'products', 'joinSelfAs' => 'product_no', 'joinOtherAs' => 'product_no'],
    ];

    public function preRestGet($queryParser, $id)
    {
        if (! TestClient::$admin) {
            $this->groupStart()->where('is_active', 1)->orWhereIn('workspace_id', TestClient::workspaceIds())->groupEnd();
        }
    }

    public function postRestGet($queryParser, $items)
    {
        if (! TestClient::$admin && $items instanceof Entity) {
            foreach ($items->all ?? [] as $item) {
                if ($item->name === null) {
                    $items->remove($item);
                }
            }
        }
    }
}
