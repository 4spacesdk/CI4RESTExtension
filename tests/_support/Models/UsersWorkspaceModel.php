<?php

namespace Tests\Support\Models;

use RestExtension\Core\Model;
use RestExtension\ResourceModelInterface;
use Tests\Support\FollowsEngine;
use Tests\Support\RulelessModel;
use Tests\Support\TestClient;

/**
 * The users_workspaces pivot as an entity, as in user-service: status, and invited_by as a second
 * relation to users.
 */
class UsersWorkspaceModel extends Model implements ResourceModelInterface
{
    use FollowsEngine;
    use RulelessModel { preRestGet as private noRules; }

    public $hasOne = [
        UserModel::class,
        WorkspaceModel::class,
        'invited_by' => ['class' => UserModel::class, 'otherField' => 'users_workspace_invited_by', 'joinTable' => 'users_workspaces', 'joinSelfAs' => 'id', 'joinOtherAs' => 'invited_by_id'],
    ];

    public function preRestGet($queryParser, $id)
    {
        if (! TestClient::$admin) {
            $this->whereIn('workspace_id', TestClient::workspaceIds());
        }
    }
}
