<?php

namespace Tests\Support\Models;

use RestExtension\Core\Model;
use RestExtension\ResourceModelInterface;
use Tests\Support\FollowsEngine;
use Tests\Support\RulelessModel;
use Tests\Support\TestClient;

/**
 * As user-service's UserModel: workspaces many-to-many, the pivot as an entity, and the pivot
 * rows a user invited others in. A caller sees themselves and the members of their workspaces.
 */
class UserModel extends Model implements ResourceModelInterface
{
    use FollowsEngine;
    use RulelessModel { preRestGet as private noRules; }

    public $hasMany = [
        WorkspaceModel::class,
        UsersWorkspaceModel::class,
        'users_workspace_invited_by' => ['class' => UsersWorkspaceModel::class, 'otherField' => 'invited_by', 'joinTable' => 'users_workspaces', 'joinSelfAs' => 'invited_by_id', 'joinOtherAs' => 'id'],
        FavoriteModel::class,
    ];

    public function preRestGet($queryParser, $id)
    {
        if (! TestClient::$admin) {
            $this->groupStart()
                ->where('id', TestClient::$userId)
                ->orWhereInRelated(UsersWorkspaceModel::class, 'workspace_id', TestClient::workspaceIds())
                ->groupEnd();
        }
    }
}
