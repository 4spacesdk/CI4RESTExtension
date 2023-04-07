<?php namespace RestExtension\Entities;

use ArrayIterator;
use OrmExtension\Extensions\Entity;

/**
 * Class ApiAccessLog
 * @package RestExtension\Entities
 * @property int $user_id
 * @property string $client_id
 * @property string $access_token
 * @property int $api_route_id
 * @property string $uri
 * @property string $date
 * @property int $milliseconds
 * @property string $ip_address
 */
class ApiAccessLog extends Entity {

    /**
     * @return \ArrayIterator|\OrmExtension\Extensions\Entity[]|\Traversable|ApiAccessLog[]
     */
    public function getIterator(): ArrayIterator {
        return parent::getIterator();
    }

}
