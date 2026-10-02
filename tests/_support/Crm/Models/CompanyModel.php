<?php

namespace Tests\Support\Crm\Models;

use RestExtension\Core\Model;
use RestExtension\ResourceModelInterface;
use Tests\Support\Crm\CrmClient;
use Tests\Support\FollowsEngine;
use Tests\Support\RulelessModel;

/**
 * A customer, owned by a team, in a group of companies, in a country by its code. A rep sees their teams' companies, and the shared ones.
 */
class CompanyModel extends Model implements ResourceModelInterface
{
    use FollowsEngine;
    use RulelessModel { preRestGet as private noRules; }

    public $hasOne = [
        'owner_team' => ['class' => TeamModel::class, 'otherField' => 'owned_company', 'joinTable' => 'companies', 'joinSelfAs' => 'id', 'joinOtherAs' => 'owner_team_id'],
        'parent' => ['class' => CompanyModel::class, 'otherField' => 'child', 'joinSelfAs' => 'id', 'joinOtherAs' => 'parent_id'],
        IndustryModel::class,
        'country' => ['class' => CountryModel::class, 'otherField' => 'company', 'joinTable' => 'companies', 'joinSelfAs' => 'code', 'joinOtherAs' => 'country_code'],
    ];
    public $hasMany = [
        'child' => ['class' => CompanyModel::class, 'otherField' => 'parent', 'joinSelfAs' => 'parent_id', 'joinOtherAs' => 'id'],
        ContactModel::class,
        DealModel::class,
        TagModel::class,
    ];

    public function preRestGet($queryParser, $id)
    {
        if ($queryParser->hasInclude('count_open_deals')) {
            $queryParser->getInclude('count_open_deals')->ignoreAuto = true;
            $deals = (new DealModel())->select('COUNT(*) as id', true, false)->whereIn('stage', ['lead', 'qualified'])->where('company_id', '${parent}.id', false);
            $this->select('*')->selectSubQuery($deals, 'count_open_deals');
        }
        if (! CrmClient::$admin) {
            $this->groupStart()->whereIn('owner_team_id', CrmClient::teamIds())->orWhere('is_shared', 1)->groupEnd();
        }
    }
}
