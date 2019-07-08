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
 * @property string $nameLoweCase
 * @property string $path
 * @property string $resourceNameLowerCase
 * @property string $resourceNameUpperCase
 * @property string $scope
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

        $className = "App\Controllers\\{$api}";
        $rc = new \ReflectionClass('\\'.$className);
        $item->name = substr($rc->getName(), strrpos($rc->getName(), '\\') + 1);
        $item->nameLoweCase = lcfirst($item->name);
        $item->resourceNameUpperCase = singular($item->name);
        $item->resourceNameLowerCase = lcfirst($item->resourceNameUpperCase);
        $item->path = "/".strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $item->name));

        foreach(explode("\n", $rc->getDocComment()) as $docComment) {
            $search = '@scope';
            $pos = strpos($docComment, $search);
            if($pos !== false) {
                $item->scope = trim(substr($docComment, $pos + strlen($search)));
            }
        }

        $item->endpoints = [];

        $idParam = new ParameterItem('id', 'path', 'integer', true);
        $filterParam = new ParameterItem('filter', 'query', 'string', false);
        $includeParam = new ParameterItem('include', 'query', 'string', false);
        $orderingParam = new ParameterItem('ordering', 'query', 'string', false);
        $offsetParam = new ParameterItem('offset', 'query', 'integer', false);
        $limitParam = new ParameterItem('limit', 'query', 'integer', false);
        $countParam = new ParameterItem('count', 'query', 'bool', false);

        $methods = $rc->getMethods(ReflectionMethod::IS_PUBLIC);
        $name2Method = [];
        foreach($methods as $method) {
            if(strcmp($method->class, $className) === 0)
                $name2Method[$method->getName()] = $method;
        }

        // Resource methods
        if($rc->getParentClass()->getName() == ResourceController::class) {
            $Resources = substr($rc->getName(), strrpos($rc->getName(), '\\') + 1); // Remove namespace
            $resources = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $Resources)); // Camel to snake
            $Resource = singular($Resources);

            $getEndpoint = new EndpointItem();
            $getEndpoint->method = 'get';
            $getEndpoint->path = "/{$resources}";
            $getEndpoint->tag = $Resources;
            $getEndpoint->parameters[] = $filterParam;
            $getEndpoint->parameters[] = $includeParam;
            $getEndpoint->parameters[] = $orderingParam;
            $getEndpoint->parameters[] = $offsetParam;
            $getEndpoint->parameters[] = $limitParam;
            $getEndpoint->parameters[] = $countParam;
            if(isset($name2Method['get'])) {
                $endpoint = EndpointItem::parse($name2Method['get']);
                if(isset($endpoint->scope)) $getEndpoint->scope = $endpoint->scope;
                if(isset($endpoint->ignore)) $getEndpoint->ignore = $endpoint->ignore;
            }
            if(isset($item->scope) && !isset($getEndpoint->scope)) $getEndpoint->scope = $item->scope;
            if(!isset($getEndpoint->ignore)) $item->endpoints[] = $getEndpoint;

            $getByIdEndpoint = new EndpointItem();
            $getByIdEndpoint->parameters[] = $idParam;
            $getByIdEndpoint->method = 'get';
            $getByIdEndpoint->path = "/{$resources}/{id}";
            $getByIdEndpoint->tag = $Resources;
            if(isset($name2Method['get'])) {
                $endpoint = EndpointItem::parse($name2Method['get']);
                if(isset($endpoint->scope)) $getByIdEndpoint->scope = $endpoint->scope;
                if(isset($endpoint->ignore)) $getByIdEndpoint->ignore = $endpoint->ignore;
            }
            if(isset($item->scope) && !isset($getByIdEndpoint->scope)) $getByIdEndpoint->scope = $item->scope;
            if(!isset($getByIdEndpoint->ignore)) $item->endpoints[] = $getByIdEndpoint;

            $postEndpoint = new EndpointItem();
            $postEndpoint->method = 'post';
            $postEndpoint->path = "/{$resources}";
            $postEndpoint->tag = $Resources;
            $postEndpoint->requestEntity = $Resource;
            if(isset($name2Method['post'])) {
                $endpoint = EndpointItem::parse($name2Method['post']);
                if(isset($endpoint->scope)) $postEndpoint->scope = $endpoint->scope;
                if(isset($endpoint->ignore)) $postEndpoint->ignore = $endpoint->ignore;
            }
            if(isset($item->scope) && !isset($postEndpoint->scope)) $postEndpoint->scope = $item->scope;
            if(!isset($postEndpoint->ignore)) $item->endpoints[] = $postEndpoint;

            $putByIdEndpoint = new EndpointItem();
            $putByIdEndpoint->parameters[] = $idParam;
            $putByIdEndpoint->method = 'put';
            $putByIdEndpoint->path = "/{$resources}/{id}";
            $putByIdEndpoint->tag = $Resources;
            $putByIdEndpoint->requestEntity = $Resource;
            if(isset($name2Method['put'])) {
                $endpoint = EndpointItem::parse($name2Method['put']);
                if(isset($endpoint->scope)) $putByIdEndpoint->scope = $endpoint->scope;
                if(isset($endpoint->ignore)) $putByIdEndpoint->ignore = $endpoint->ignore;
            }
            if(isset($item->scope) && !isset($putByIdEndpoint->scope)) $putByIdEndpoint->scope = $item->scope;
            if(!isset($putByIdEndpoint->ignore)) $item->endpoints[] = $putByIdEndpoint;

            $putEndpoint = new EndpointItem();
            $putEndpoint->method = 'put';
            $putEndpoint->path = "/{$resources}";
            $putEndpoint->tag = $Resources;
            $putEndpoint->requestEntity = $Resource;
            if(isset($name2Method['put'])) {
                $endpoint = EndpointItem::parse($name2Method['put']);
                if(isset($endpoint->scope)) $putEndpoint->scope = $endpoint->scope;
                if(isset($endpoint->ignore)) $putEndpoint->ignore = $endpoint->ignore;
            }
            if(isset($item->scope) && !isset($putEndpoint->scope)) $putEndpoint->scope = $item->scope;
            if(!isset($putEndpoint->ignore)) $item->endpoints[] = $putEndpoint;

            $patchByIdendpoint = new EndpointItem();
            $patchByIdendpoint->parameters[] = $idParam;
            $patchByIdendpoint->method = 'patch';
            $patchByIdendpoint->path = "/{$resources}/{id}";
            $patchByIdendpoint->tag = $Resources;
            $patchByIdendpoint->requestEntity = $Resource;
            if(isset($name2Method['patch'])) {
                $endpoint = EndpointItem::parse($name2Method['patch']);
                if(isset($endpoint->scope)) $patchByIdendpoint->scope = $endpoint->scope;
                if(isset($endpoint->ignore)) $patchByIdendpoint->ignore = $endpoint->ignore;
            }
            if(isset($item->scope) && !isset($patchByIdendpoint->scope)) $patchByIdendpoint->scope = $item->scope;
            if(!isset($patchByIdendpoint->ignore)) $item->endpoints[] = $patchByIdendpoint;

            $patchEndpoint = new EndpointItem();
            $patchEndpoint->method = 'patch';
            $patchEndpoint->path = "/{$resources}";
            $patchEndpoint->tag = $Resources;
            $patchEndpoint->requestEntity = $Resource;
            if(isset($name2Method['patch'])) {
                $endpoint = EndpointItem::parse($name2Method['patch']);
                if(isset($endpoint->scope)) $patchEndpoint->scope = $endpoint->scope;
                if(isset($endpoint->ignore)) $patchEndpoint->ignore = $endpoint->ignore;
            }
            if(isset($item->scope) && !isset($patchEndpoint->scope)) $patchEndpoint->scope = $item->scope;
            if(!isset($patchEndpoint->ignore)) $item->endpoints[] = $patchEndpoint;

            $deleteEndpoint = new EndpointItem();
            $deleteEndpoint->parameters[] = $idParam;
            $deleteEndpoint->method = 'delete';
            $deleteEndpoint->path = "/{$resources}/{id}";
            $deleteEndpoint->tag = $Resources;
            if(isset($name2Method['delete'])) {
                $endpoint = EndpointItem::parse($name2Method['delete']);
                if(isset($endpoint->scope)) $deleteEndpoint->scope = $endpoint->scope;
                if(isset($endpoint->ignore)) $deleteEndpoint->ignore = $endpoint->ignore;
            }
            if(isset($item->scope) && !isset($deleteEndpoint->scope)) $deleteEndpoint->scope = $item->scope;
            if(!isset($deleteEndpoint->ignore)) $item->endpoints[] = $deleteEndpoint;
        }
        
        foreach($methods as $method) {

            $validate = EndpointItem::validate($method);
            if($validate) {
                $endpoint = EndpointItem::parse($method, $validate);
                if($endpoint->method == 'get' &! $endpoint->custom) {
                    $endpoint->parameters[] = $filterParam;
                    $endpoint->parameters[] = $includeParam;
                    $endpoint->parameters[] = $orderingParam;
                    $endpoint->parameters[] = $limitParam;
                    $endpoint->parameters[] = $offsetParam;
                    $endpoint->parameters[] = $countParam;
                }

                if($item->scope && !isset($endpoint->scope))
                    $endpoint->scope = $item->scope;

                $item->endpoints[] = $endpoint;
            }
        }

        return $item;
    }

}
