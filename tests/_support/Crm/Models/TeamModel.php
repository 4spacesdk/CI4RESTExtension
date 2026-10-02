<?php

namespace Tests\Support\Crm\Models;

use RestExtension\Core\Model;
use RestExtension\ResourceModelInterface;
use Tests\Support\Crm\CrmClient;
use Tests\Support\FollowsEngine;
use Tests\Support\RulelessModel;

/**
 * A sales team, in a hierarchy: a rep sees their teams and every team under them.
 */
class TeamModel extends Model implements ResourceModelInterface
{
    use FollowsEngine;
    use RulelessModel { preRestGet as private noRules; }

    public $hasOne = [
        'parent' => ['class' => TeamModel::class, 'otherField' => 'child', 'joinSelfAs' => 'id', 'joinOtherAs' => 'parent_id'],
        'lead' => ['class' => RepModel::class, 'otherField' => 'led_team', 'joinTable' => 'teams', 'joinSelfAs' => 'id', 'joinOtherAs' => 'lead_rep_id'],
    ];
    public $hasMany = [
        'child' => ['class' => TeamModel::class, 'otherField' => 'parent', 'joinSelfAs' => 'parent_id', 'joinOtherAs' => 'id'],
        RepModel::class => ['class' => RepModel::class, 'otherField' => TeamModel::class, 'joinTable' => 'team_members', 'joinSelfAs' => 'team_id', 'joinOtherAs' => 'rep_id'],
        TeamMemberModel::class,
        'owned_company' => ['class' => CompanyModel::class, 'otherField' => 'owner_team', 'joinTable' => 'companies', 'joinSelfAs' => 'owner_team_id', 'joinOtherAs' => 'id'],
        'home_rep' => ['class' => RepModel::class, 'otherField' => 'home_team', 'joinTable' => 'reps', 'joinSelfAs' => 'home_team_id', 'joinOtherAs' => 'id'],
    ];

    public function preRestGet($queryParser, $id)
    {
        if (! CrmClient::$admin) {
            $this->whereIn('id', CrmClient::teamIds());
        }
    }
}
