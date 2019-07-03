<?php namespace RestExtension\Entities;

use OrmExtension\Extensions\Entity;

/**
 * Class ApiBlockedLog
 * @package RestExtension\Entities
 * @property int $user_id
 * @property string $client_id
 * @property string $access_token
 * @property int $api_route_id
 * @property string $uri
 * @property string $date
 * @property string $reason
 * @property string $ip_address
 */
class ApiBlockedLog extends Entity {

}
