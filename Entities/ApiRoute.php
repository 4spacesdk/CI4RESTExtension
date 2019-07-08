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

    public static function quick($from, $toController, $toMethod, $method = 'get', $scope = '') {
        $route = new ApiRoute();
        $route->method = $method;
        $route->from = $from;
        $route->to = "${toController}::{$toMethod}";
        $route->scope = $scope;
        $route->saveUnique();
    }

    public function saveUnique() {
        $this->from = trim($this->from, '/');

        /** @var ApiRoute $route */
        $route = (new ApiRouteModel())
            ->where('method', $this->method)
            ->where('from', $this->from)
            ->find();
        if($route->exists())
            $this->id = $route->id;
        $this->save();
    }

    public static function addResourceController($class, $scope = '') {
        $Resource = substr($class, strrpos($class, '\\') + 1); // Remove namespace
        $resource = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $Resource)); // Camel to snake
        ApiRoute::quick($resource, $class, 'get', 'get', $scope);
        ApiRoute::quick("$resource/([0-9]+)", $class, "get/$1", 'get', $scope);
        ApiRoute::quick($resource, $class, 'post', 'post', $scope);
        ApiRoute::quick("$resource/([0-9]+)", $class, "put/$1", 'put', $scope);
        ApiRoute::quick($resource, $class, 'put', 'put', $scope);
        ApiRoute::quick("$resource/([0-9]+)", $class, "patch/$1", 'patch', $scope);
        ApiRoute::quick($resource, $class, 'patch', 'patch', $scope);
        ApiRoute::quick("$resource/([0-9]+)", $class, "delete/$1", 'delete', $scope);
    }

    /**
     * @return \ArrayIterator|Entity[]|\Traversable|ApiRoute[]
     */
    public function getIterator() {return parent::getIterator();}

}
