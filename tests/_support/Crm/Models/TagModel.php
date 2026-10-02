<?php

namespace Tests\Support\Crm\Models;

use RestExtension\Core\Model;
use RestExtension\ResourceModelInterface;
use Tests\Support\Crm\CrmClient;
use Tests\Support\FollowsEngine;
use Tests\Support\RulelessModel;

/**
 * A tag on companies. Everyone sees every tag.
 */
class TagModel extends Model implements ResourceModelInterface
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
