<?php

namespace Tests\Support\Crm\Models;

use RestExtension\Core\Model;
use RestExtension\ResourceModelInterface;
use Tests\Support\Crm\CrmClient;
use Tests\Support\FollowsEngine;
use Tests\Support\RulelessModel;

/**
 * A person at a company, owned by a rep. A rep sees their own contacts and those of their teams' companies - not those of a shared company.
 */
class ContactModel extends Model implements ResourceModelInterface
{
    use FollowsEngine;
    use RulelessModel { preRestGet as private noRules; }

    public $hasOne = [
        CompanyModel::class,
        'owner' => ['class' => RepModel::class, 'otherField' => 'owned_contact', 'joinTable' => 'contacts', 'joinSelfAs' => 'id', 'joinOtherAs' => 'owner_rep_id'],
    ];
    public $hasMany = [
        DealModel::class => ['class' => DealModel::class, 'otherField' => 'participant', 'joinTable' => 'deal_participants', 'joinSelfAs' => 'contact_id', 'joinOtherAs' => 'deal_id'],
        ActivityModel::class,
        'primary_deal' => ['class' => DealModel::class, 'otherField' => 'primary_contact', 'joinTable' => 'deals', 'joinSelfAs' => 'primary_contact_id', 'joinOtherAs' => 'id'],
    ];

    public function preRestGet($queryParser, $id)
    {
        if (! CrmClient::$admin) {
            $this->groupStart()
                ->where('owner_rep_id', CrmClient::$repId)
                ->orWhereInRelated(CompanyModel::class, 'owner_team_id', CrmClient::teamIds())
                ->groupEnd();
        }
    }
}
