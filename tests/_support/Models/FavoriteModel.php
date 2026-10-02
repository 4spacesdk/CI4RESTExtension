<?php

namespace Tests\Support\Models;

use RestExtension\Core\Model;
use RestExtension\ResourceModelInterface;
use Tests\Support\FollowsEngine;
use Tests\Support\RulelessModel;
use Tests\Support\TestClient;

/**
 * As product-service's FavoriteModel: the product by product_no. A caller sees their own.
 */
class FavoriteModel extends Model implements ResourceModelInterface
{
    use FollowsEngine;
    use RulelessModel { preRestGet as private noRules; }

    public $hasOne = [
        UserModel::class,
        'product' => ['class' => ProductModel::class, 'otherField' => 'favorite_product', 'joinTable' => 'products', 'joinSelfAs' => 'product_no', 'joinOtherAs' => 'product_no'],
    ];

    public function preRestGet($queryParser, $id)
    {
        if (! TestClient::$admin) {
            $this->where('user_id', TestClient::$userId);
        }
    }
}
