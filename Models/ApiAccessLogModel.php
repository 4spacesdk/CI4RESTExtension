<?php namespace RestExtension\Models;

use RestExtension\Core\Model;

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
