<?php namespace RestExtension\Models;

use RestExtension\Core\Model;

/**
 * Class ApiRouteModel
 * @package RestExtension\Models
 */
class ApiRouteModel extends Model {

    public $table = 'api_routes';
    public $entityName = 'ApiRoute';
    public $returnType = '\RestExtension\Entities\ApiRoute';
    public $allowedFields = [
        "id",
        "method",
        "from",
        "to",
        "cacheable",
        "version",
        "scope",
        "is_public",
    ];
    
    public $hasOne = [
        
    ];
    
    public $hasMany = [
        ApiAccessLogModel::class,
        ApiErrorLogModel::class,
        ApiBlockedLogModel::class
    ];
    
}
