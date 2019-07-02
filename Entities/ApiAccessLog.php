<?php namespace RestExtension\Entities;

use OrmExtension\Extensions\Entity;

/**
 * Class ApiAccessLog
 * @package RestExtension\Entities
 * @property string $client_id
 * @property string $access_token
 * @property int $api_route_id
 * @property string $uri
 * @property string $date
 * @property int $milliseconds
 * @property string $ip_address
 */
class ApiAccessLog extends Entity {

}
