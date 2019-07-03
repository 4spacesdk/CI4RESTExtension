<?php namespace RestExtension\Entities;

use OrmExtension\Extensions\Entity;

/**
 * Class ApiErrorLog
 * @package RestExtension\Entities
 * @property int $user_id
 * @property string $client_id
 * @property string $access_token
 * @property int $api_route_id
 * @property string $uri
 * @property string $date
 * @property int $code
 * @property string $message
 * @property string $ip_address
 * @property string $get
 * @property string $post
 * @property string $patch
 * @property string $put
 * @property string $headers
 */
class ApiErrorLog extends Entity {

}
