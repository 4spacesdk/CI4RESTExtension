<?php

namespace Tests\Support\Crm\Models;

use RestExtension\Core\Model;
use RestExtension\ResourceModelInterface;
use Tests\Support\Crm\CrmClient;
use Tests\Support\FollowsEngine;
use Tests\Support\RulelessModel;

/**
 * A call, mail, meeting or note, on a deal, a contact or both, by a rep. A rep sees their own, those on their deals, and those on their teams' companies' deals.
 */
class ActivityModel extends Model implements ResourceModelInterface
{
    use FollowsEngine;
    use RulelessModel { preRestGet as private noRules; }

    public $hasOne = [
        DealModel::class,
        ContactModel::class,
        'author' => ['class' => RepModel::class, 'otherField' => 'authored_activity', 'joinTable' => 'activities', 'joinSelfAs' => 'id', 'joinOtherAs' => 'author_rep_id'],
    ];
    public $hasMany = [

    ];

    public function preRestGet($queryParser, $id)
    {
        if (! CrmClient::$admin) {
            $this->groupStart()
                ->where('author_rep_id', CrmClient::$repId)
                ->orWhereRelated(DealModel::class, 'owner_rep_id', CrmClient::$repId)
                ->orWhereInRelated([DealModel::class, CompanyModel::class], 'owner_team_id', CrmClient::teamIds())
                ->groupEnd();
        }
    }
}
