<?php namespace RestExtension\Models;

use OrmExtension\Extensions\Model;

/**
 * Class ApiBlockedLogModel
 * @package RestExtension\Models
 */
class ApiBlockedLogModel extends Model {
    
    public $hasOne = [
        OAuthClientModel::class,
        ApiRouteModel::class
    ];
    
    public $hasMany = [
        
    ];
    
}
