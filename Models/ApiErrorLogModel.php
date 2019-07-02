<?php namespace RestExtension\Models;

use OrmExtension\Extensions\Model;

/**
 * Class ApiErrorLogModel
 * @package RestExtension\Models
 */
class ApiErrorLogModel extends Model {
    
    public $hasOne = [
        OAuthClientModel::class,
        ApiRouteModel::class
    ];
    
    public $hasMany = [
        
    ];
    
}
