<?php

namespace Tests\Support\Crm\Models;

use RestExtension\Core\Model;
use RestExtension\ResourceModelInterface;
use Tests\Support\Crm\CrmClient;
use Tests\Support\FollowsEngine;
use Tests\Support\RulelessModel;

/**
 * The team_members pivot as an entity: a role, soft deleted when someone leaves.
 */
class TeamMemberModel extends Model implements ResourceModelInterface
{
    use FollowsEngine;
    use RulelessModel { preRestGet as private noRules; }

    public $hasOne = [
        RepModel::class,
        TeamModel::class,
    ];
    public $hasMany = [

    ];

    public function preRestGet($queryParser, $id)
    {
        if (! CrmClient::$admin) {
            $this->whereIn('team_id', CrmClient::teamIds());
        }
    }
}
