<?php namespace RestExtension\Models;

use RestExtension\Core\Model;

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
