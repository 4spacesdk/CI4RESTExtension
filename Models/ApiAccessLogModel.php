<?php namespace RestExtension\Models;

use RestExtension\Core\Model;

/**
 * Class ApiAccessLogModel
 * @package RestExtension\Models
 */
class ApiAccessLogModel extends Model {
    
    public $hasOne = [
        ApiRouteModel::class
    ];
    
    public $hasMany = [
        
    ];
    
}
