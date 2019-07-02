<?php namespace RestExtension\Models;

use OrmExtension\Extensions\Model;

/**
 * Class OAuthClientModel
 * @package RestExtension\Models
 */
class OAuthClientModel extends Model {

    protected $primaryKey = 'client_id';

    public function getTableName() {
        return 'oauth_clients';
    }

    public $hasOne = [

    ];

    public $hasMany = [
        ApiAccessLogModel::class,
        ApiBlockedLogModel::class,
        ApiErrorLogModel::class,
        ApiUsageReportModel::class
    ];

}
