<?php

namespace Tests\Support\Models;

use RestExtension\Core\Model;
use RestExtension\ResourceModelInterface;
use Tests\Support\FollowsEngine;
use Tests\Support\RulelessModel;

/**
 * A tree, as user-service's GroupModel: parent and child are the same table, by parent_id.
 */
class GroupModel extends Model implements ResourceModelInterface
{
    use FollowsEngine;
    use RulelessModel;

    public $hasOne = [
        'parent' => ['class' => GroupModel::class, 'otherField' => 'child', 'joinSelfAs' => 'id', 'joinOtherAs' => 'parent_id'],
    ];
    public $hasMany = [
        'child' => ['class' => GroupModel::class, 'otherField' => 'parent', 'joinSelfAs' => 'parent_id', 'joinOtherAs' => 'id'],
        WorkspaceModel::class,
    ];
}
