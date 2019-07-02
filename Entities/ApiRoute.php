<?php namespace RestExtension\Entities;

use OrmExtension\Extensions\Entity;
use RestExtension\Models\ApiRouteModel;

/**
 * Class ApiRoute
 * @package RestExtension\Entities
 * @property string $method
 * @property string $from
 * @property string $to
 * @property bool $cacheable
 * @property string $version
 * @property string $scope
 * @property string $is_public
 *
 * Many
 * @property ApiAccessLog $api_access_logs
 * @property ApiBlockedLog $api_blocked_logs
 * @property ApiErrorLog $api_error_logs
 */
class ApiRoute extends Entity {

    public function saveUnique() {
        $this->from = trim($this->from, '/');

        /** @var ApiRoute $route */
        $route = (new ApiRouteModel())
            ->where('method', $this->method)
            ->where('from', $this->from)
            ->find();
        if($route->exists())
            $this->id = $route;
        $this->save();
    }

    /**
     * @return \ArrayIterator|Entity[]|\Traversable|ApiRoute[]
     */
    public function getIterator() {return parent::getIterator();}

}
