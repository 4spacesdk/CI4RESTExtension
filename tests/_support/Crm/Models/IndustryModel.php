<?php

namespace Tests\Support\Crm\Models;

use RestExtension\Core\Model;
use RestExtension\ResourceModelInterface;
use Tests\Support\Crm\CrmClient;
use Tests\Support\FollowsEngine;
use Tests\Support\RulelessModel;

/**
 * An industry. Everyone sees every industry.
 */
class IndustryModel extends Model implements ResourceModelInterface
{
    use FollowsEngine;
    use RulelessModel { preRestGet as private noRules; }

    public $hasOne = [

    ];
    public $hasMany = [
        CompanyModel::class,
    ];

    public function preRestGet($queryParser, $id)
    {

    }
}
