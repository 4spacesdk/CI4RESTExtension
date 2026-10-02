<?php

namespace Tests\Support\Crm\Models;

use RestExtension\Core\Model;
use RestExtension\ResourceModelInterface;
use Tests\Support\Crm\CrmClient;
use Tests\Support\FollowsEngine;
use Tests\Support\RulelessModel;

/**
 * A country, which companies name by its code, not its id. Everyone sees every country.
 */
class CountryModel extends Model implements ResourceModelInterface
{
    use FollowsEngine;
    use RulelessModel { preRestGet as private noRules; }

    public $hasOne = [

    ];
    public $hasMany = [
        'company' => ['class' => CompanyModel::class, 'otherField' => 'country', 'joinTable' => 'companies', 'joinSelfAs' => 'country_code', 'joinOtherAs' => 'code'],
    ];

    public function preRestGet($queryParser, $id)
    {

    }
}
