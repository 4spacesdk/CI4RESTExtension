<?php namespace RestExtension\Models;

use OrmExtension\Extensions\Model;

/**
 * Class DeletionModel
 * @package RestExtension\Models
 */
class DeletionModel extends Model {
    
    public $hasOne = [
        
    ];
    
    public $hasMany = [
        OAuthClientModel::class
    ];
    
}
