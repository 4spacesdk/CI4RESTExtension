<?php namespace RestExtension\Models;

use OrmExtension\Extensions\Model;

/**
 * Class ApiAccessLogModel
 * @package RestExtension\Models
 */
class ApiAccessLogModel extends Model {
    
    public $hasOne = [
        OAuthClientModel::class,
        ApiRouteModel::class
    ];
    
    public $hasMany = [
        
    ];
    
}
