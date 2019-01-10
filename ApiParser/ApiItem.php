<?php namespace RestExtension\ApiParser;
use App\Core\ResourceController;
use ReflectionMethod;

/**
 * Created by PhpStorm.
 * User: martin
 * Date: 27/11/2018
 * Time: 08.33
 *
 * @property string $name
 * @property string $path
 * @property EndpointItem[] $endpoints
 */
class ApiItem {

    /**
     * @param $api
     * @return ApiItem
     * @throws \ReflectionException
     */
    public static function parse($api) {
        $item = new ApiItem();
        $item->path = $api;

        $rc = new \ReflectionClass("\App\Controllers\\{$api}");
        $item->name = substr($rc->getName(), strrpos($rc->getName(), '\\') + 1);
        $item->path = "/".strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $item->name));

        $item->endpoints = [];


        $idParam = new ParameterItem('id', 'path', 'integer', true);
        $filterParam = new ParameterItem('filter', 'query', 'string', false);
        $includeParam = new ParameterItem('include', 'query', 'string', false);
        $orderingParam = new ParameterItem('ordering', 'query', 'string', false);
        $offsetParam = new ParameterItem('offset', 'query', 'integer', false);
        $limitParam = new ParameterItem('limit', 'query', 'integer', false);
        $countParam = new ParameterItem('count', 'query', 'bool', false);

        // Resource methods
        if($rc->getParentClass()->getName() == ResourceController::class) {
            $Resources = substr($rc->getName(), strrpos($rc->getName(), '\\') + 1); // Remove namespace
            $resources = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $Resources)); // Camel to snake
            $Resource = singular($Resources);

            $endpoint = new EndpointItem();
            $endpoint->method = 'get';
            $endpoint->path = "/{$resources}";
            $endpoint->tag = $Resources;
            $endpoint->parameters[] = $filterParam;
            $endpoint->parameters[] = $includeParam;
            $endpoint->parameters[] = $orderingParam;
            $endpoint->parameters[] = $offsetParam;
            $endpoint->parameters[] = $limitParam;
            $endpoint->parameters[] = $countParam;
            $item->endpoints[] = $endpoint;
            $endpoint = new EndpointItem();
            $endpoint->method = 'get';
            $endpoint->path = "/{$resources}/{id}";
            $endpoint->tag = $Resources;
            $endpoint->parameters[] = $idParam;
            $item->endpoints[] = $endpoint;
            $endpoint = new EndpointItem();
            $endpoint->method = 'post';
            $endpoint->path = "/{$resources}";
            $endpoint->tag = $Resources;
            $endpoint->requestEntity = $Resource;
            $item->endpoints[] = $endpoint;
            $endpoint = new EndpointItem();
            $endpoint->method = 'put';
            $endpoint->path = "/{$resources}/{id}";
            $endpoint->tag = $Resources;
            $endpoint->parameters[] = $idParam;
            $endpoint->requestEntity = $Resource;
            $item->endpoints[] = $endpoint;
            $endpoint = new EndpointItem();
            $endpoint->method = 'put';
            $endpoint->path = "/{$resources}";
            $endpoint->tag = $Resources;
            $endpoint->requestEntity = $Resource;
            $item->endpoints[] = $endpoint;
            $endpoint = new EndpointItem();
            $endpoint->method = 'patch';
            $endpoint->path = "/{$resources}/{id}";
            $endpoint->tag = $Resources;
            $endpoint->parameters[] = $idParam;
            $endpoint->requestEntity = $Resource;
            $item->endpoints[] = $endpoint;
            $endpoint = new EndpointItem();
            $endpoint->method = 'patch';
            $endpoint->path = "/{$resources}";
            $endpoint->tag = $Resources;
            $endpoint->requestEntity = $Resource;
            $item->endpoints[] = $endpoint;
            $endpoint = new EndpointItem();
            $endpoint->method = 'delete';
            $endpoint->path = "/{$resources}/{id}";
            $endpoint->tag = $Resources;
            $endpoint->parameters[] = $idParam;
            $item->endpoints[] = $endpoint;
        }

        $methods = $rc->getMethods(ReflectionMethod::IS_PUBLIC);
        foreach($methods as $method) {
            $endpoint = EndpointItem::parse($method);
            if($endpoint) {
                if($endpoint->method == 'get' &! $endpoint->custom) {
                    $endpoint->parameters[] = $filterParam;
                    $endpoint->parameters[] = $includeParam;
                    $endpoint->parameters[] = $orderingParam;
                    $endpoint->parameters[] = $limitParam;
                    $endpoint->parameters[] = $offsetParam;
                    $endpoint->parameters[] = $countParam;
                }

                $item->endpoints[] = $endpoint;
            }
        }

        return $item;
    }

}