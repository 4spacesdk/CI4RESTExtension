<?php namespace RestExtension\Models;

use OrmExtension\Extensions\Model;

/**
 * Class ApiRouteModel
 * @package RestExtension\Models
 */
class ApiRouteModel extends Model {
    
    public $hasOne = [
        
    ];
    
    public $hasMany = [
        ApiAccessLogModel::class,
        ApiErrorLogModel::class,
        ApiBlockedLogModel::class
    ];
    
}
