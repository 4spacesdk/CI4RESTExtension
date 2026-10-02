<?php

namespace Tests\Support\Crm\Models;

use RestExtension\Core\Model;
use RestExtension\ResourceModelInterface;
use Tests\Support\Crm\CrmClient;
use Tests\Support\FollowsEngine;
use Tests\Support\RulelessModel;

/**
 * A deal with a company, owned by a rep, with a primary contact and participants through a pivot of its own name. A rep sees their own deals, and their teams' companies' deals unless private.
 */
class DealModel extends Model implements ResourceModelInterface
{
    use FollowsEngine;
    use RulelessModel { preRestGet as private noRules; }

    public $hasOne = [
        CompanyModel::class,
        'owner' => ['class' => RepModel::class, 'otherField' => 'owned_deal', 'joinTable' => 'deals', 'joinSelfAs' => 'id', 'joinOtherAs' => 'owner_rep_id'],
        'primary_contact' => ['class' => ContactModel::class, 'otherField' => 'primary_deal', 'joinTable' => 'deals', 'joinSelfAs' => 'id', 'joinOtherAs' => 'primary_contact_id'],
    ];
    public $hasMany = [
        'participant' => ['class' => ContactModel::class, 'otherField' => DealModel::class, 'joinTable' => 'deal_participants', 'joinSelfAs' => 'deal_id', 'joinOtherAs' => 'contact_id'],
        ActivityModel::class,
    ];

    public function preRestGet($queryParser, $id)
    {
        if (! CrmClient::$admin) {
            $this->groupStart()
                ->where('owner_rep_id', CrmClient::$repId)
                ->orGroupStart()
                    ->whereInRelated(CompanyModel::class, 'owner_team_id', CrmClient::teamIds())
                    ->where('is_private', 0)
                ->groupEnd()
                ->groupEnd();
        }
    }
}
