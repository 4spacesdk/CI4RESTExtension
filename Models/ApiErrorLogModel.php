<?php namespace RestExtension\Models;

use RestExtension\Core\Model;

/**
 * Class ApiErrorLogModel
 * @package RestExtension\Models
 */
class ApiErrorLogModel extends Model {
    
    public $hasOne = [
        ApiRouteModel::class
    ];
    
    public $hasMany = [
        
    ];
    
}
