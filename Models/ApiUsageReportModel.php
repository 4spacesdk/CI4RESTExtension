<?php namespace RestExtension\Models;

use OrmExtension\Extensions\Model;

/**
 * Class ApiUsageReportModel
 * @package RestExtension\Models
 */
class ApiUsageReportModel extends Model {
    
    public $hasOne = [
        OAuthClientModel::class
    ];
    
    public $hasMany = [
        
    ];
    
}
