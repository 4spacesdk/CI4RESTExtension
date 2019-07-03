<?php namespace RestExtension\Models;

use RestExtension\Core\Model;

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
