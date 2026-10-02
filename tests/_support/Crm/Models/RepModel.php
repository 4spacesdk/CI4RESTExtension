<?php

namespace Tests\Support\Crm\Models;

use RestExtension\Core\Model;
use RestExtension\ResourceModelInterface;
use Tests\Support\Crm\CrmClient;
use Tests\Support\FollowsEngine;
use Tests\Support\RulelessModel;

/**
 * A sales rep: a member of teams through team_members, with a home team. A rep sees themself and everyone in their teams.
 */
class RepModel extends Model implements ResourceModelInterface
{
    use FollowsEngine;
    use RulelessModel { preRestGet as private noRules; }

    public $hasOne = [
        'home_team' => ['class' => TeamModel::class, 'otherField' => 'home_rep', 'joinTable' => 'reps', 'joinSelfAs' => 'id', 'joinOtherAs' => 'home_team_id'],
    ];
    public $hasMany = [
        TeamModel::class => ['class' => TeamModel::class, 'otherField' => RepModel::class, 'joinTable' => 'team_members', 'joinSelfAs' => 'rep_id', 'joinOtherAs' => 'team_id'],
        TeamMemberModel::class,
        'led_team' => ['class' => TeamModel::class, 'otherField' => 'lead', 'joinTable' => 'teams', 'joinSelfAs' => 'lead_rep_id', 'joinOtherAs' => 'id'],
        'owned_contact' => ['class' => ContactModel::class, 'otherField' => 'owner', 'joinTable' => 'contacts', 'joinSelfAs' => 'owner_rep_id', 'joinOtherAs' => 'id'],
        'owned_deal' => ['class' => DealModel::class, 'otherField' => 'owner', 'joinTable' => 'deals', 'joinSelfAs' => 'owner_rep_id', 'joinOtherAs' => 'id'],
        'authored_activity' => ['class' => ActivityModel::class, 'otherField' => 'author', 'joinTable' => 'activities', 'joinSelfAs' => 'author_rep_id', 'joinOtherAs' => 'id'],
    ];

    public function preRestGet($queryParser, $id)
    {
        if (! CrmClient::$admin) {
            $this->groupStart()
                ->where('id', CrmClient::$repId)
                ->orWhereInRelated(TeamMemberModel::class, 'team_id', CrmClient::teamIds())
                ->groupEnd();
        }
    }
}
