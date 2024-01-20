<?php namespace RestExtension\Entities;

use ArrayIterator;
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

    public static function quick($from, $toController, $toMethod, $method = 'get', $scope = ''): ApiRoute {
        $route = new ApiRoute();
        $route->method = $method;
        $route->from = $from;
        $route->to = "{$toController}::{$toMethod}";
        $route->scope = $scope;
        $route->saveUnique();
        return $route;
    }

    public static function public($from, $toController, $toMethod, $method = 'get', $scope = ''): ApiRoute {
        $route = new ApiRoute();
        $route->method = $method;
        $route->from = $from;
        $route->to = "{$toController}::{$toMethod}";
        $route->scope = $scope;
        $route->is_public = true;
        $route->saveUnique();
        return $route;
    }

    public function saveUnique() {
        if(strlen($this->from) > 1)
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
        self::addResourceControllerGet($class, $scope);
        self::addResourceControllerPost($class, $scope);
        self::addResourceControllerPut($class, $scope);
        self::addResourceControllerPatch($class, $scope);
        self::addResourceControllerDelete($class, $scope);
    }

    public static function addResourceControllerGet($class, $scope = '') {
        $Resource = substr($class, strrpos($class, '\\') + 1); // Remove namespace
        $resource = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $Resource)); // Camel to snake
        ApiRoute::quick($resource, $class, 'get', 'get', $scope);
        ApiRoute::quick("$resource/([0-9]+)", $class, "get/$1", 'get', $scope);
    }

    public static function addResourceControllerPost($class, $scope = '') {
        $Resource = substr($class, strrpos($class, '\\') + 1); // Remove namespace
        $resource = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $Resource)); // Camel to snake
        ApiRoute::quick($resource, $class, 'post', 'post', $scope);
    }

    public static function addResourceControllerPut($class, $scope = '') {
        $Resource = substr($class, strrpos($class, '\\') + 1); // Remove namespace
        $resource = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $Resource)); // Camel to snake
        ApiRoute::quick("$resource/([0-9]+)", $class, "put/$1", 'put', $scope);
        ApiRoute::quick($resource, $class, 'put', 'put', $scope);
    }

    public static function addResourceControllerPatch($class, $scope = '') {
        $Resource = substr($class, strrpos($class, '\\') + 1); // Remove namespace
        $resource = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $Resource)); // Camel to snake
        ApiRoute::quick("$resource/([0-9]+)", $class, "patch/$1", 'patch', $scope);
        ApiRoute::quick($resource, $class, 'patch', 'patch', $scope);
    }

    public static function addResourceControllerDelete($class, $scope = '') {
        $Resource = substr($class, strrpos($class, '\\') + 1); // Remove namespace
        $resource = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $Resource)); // Camel to snake
        ApiRoute::quick("$resource/([0-9]+)", $class, "delete/$1", 'delete', $scope);
    }

    /**
     * @return \ArrayIterator|Entity[]|\Traversable|ApiRoute[]
     */
    public function getIterator(): ArrayIterator {return parent::getIterator();}

}
