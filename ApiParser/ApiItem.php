<?php namespace RestExtension\ApiParser;
use App\Core\ResourceController;
use Config\RestExtension;
use Config\Services;
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
 * @property bool $isResourceController
 * @property string[] $imports
 */
class ApiItem {

    public $imports = [];

    /**
     * @param $api
     * @return ApiItem
     * @throws \ReflectionException
     */
    public static function parse($api) {
        $item = new ApiItem();
        $item->path = $api;

        /** @var RestExtension $config */
        $config = config('RestExtension');
        $className = "{$config->apiControllerNamespace}{$api}";
        $rc = new \ReflectionClass('\\'.$className);
        $item->name = substr($rc->getName(), strrpos($rc->getName(), '\\') + 1);
        $item->nameLoweCase = lcfirst($item->name);
        $item->resourceNameUpperCase = singular($item->name);
        $item->resourceNameLowerCase = lcfirst($item->resourceNameUpperCase);
        $item->path = "/".strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $item->name));

        $className = $rc->getName();
        $item->name = str_replace([$config->apiControllerNamespace, '\\'], '', $className);

        foreach(explode("\n", $rc->getDocComment()) as $docComment) {
            $search = '@scope';
            $pos = strpos($docComment, $search);
            if($pos !== false) {
                $item->scope = trim(substr($docComment, $pos + strlen($search)));
            }

            $search = '@resource';
            $pos = strpos($docComment, $search);
            if($pos !== false) {
                $item->resourceNameUpperCase = trim(substr($docComment, $pos + strlen($search)));
                $item->resourceNameLowerCase = lcfirst($item->resourceNameUpperCase);
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
        $item->isResourceController = $rc->getParentClass()->getName() == ResourceController::class;
        if($item->isResourceController) {
            $Resources = substr($rc->getName(), strrpos($rc->getName(), '\\') + 1); // Remove namespace
            $tag = str_replace([$config->apiControllerNamespace, '\\'], '', $rc->getName());
            $resources = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $Resources)); // Camel to snake
            $Resource = singular($Resources);

            $overrides = [
                'scope', 'ignore', 'summary', 'requestEntity', 'responseSchema'
            ];

            $getEndpoint = new EndpointItem();
            $getEndpoint->method = 'get';
            $getEndpoint->path = "/{$resources}";
            $getEndpoint->tag = $tag;
            $getEndpoint->parameters[] = $filterParam;
            $getEndpoint->parameters[] = $includeParam;
            $getEndpoint->parameters[] = $orderingParam;
            $getEndpoint->parameters[] = $offsetParam;
            $getEndpoint->parameters[] = $limitParam;
            $getEndpoint->parameters[] = $countParam;
            $getEndpoint->responseSchema = $Resource;
            if(isset($name2Method['get'])) {
                $endpoint = EndpointItem::parse($name2Method['get']);
                foreach($overrides as $override) {
                    if(isset($endpoint->{$override}))
                        $getEndpoint->{$override} = $endpoint->{$override};
                }
            }
            if(isset($item->scope) && !isset($getEndpoint->scope)) $getEndpoint->scope = $item->scope;
            if(!isset($getEndpoint->ignore)) $item->endpoints[] = $getEndpoint;

            $getByIdEndpoint = new EndpointItem();
            $getByIdEndpoint->parameters[] = $idParam;
            $getByIdEndpoint->method = 'get';
            $getByIdEndpoint->path = "/{$resources}/{id}";
            $getByIdEndpoint->tag = $tag;
            $getByIdEndpoint->parameters[] = $includeParam;
            $getByIdEndpoint->responseSchema = $Resource;
            if(isset($name2Method['get'])) {
                $endpoint = EndpointItem::parse($name2Method['get']);
                foreach($overrides as $override) {
                    if(isset($endpoint->{$override}))
                        $getByIdEndpoint->{$override} = $endpoint->{$override};
                }
            }
            if(isset($item->scope) && !isset($getByIdEndpoint->scope)) $getByIdEndpoint->scope = $item->scope;
            if(!isset($getByIdEndpoint->ignore)) $item->endpoints[] = $getByIdEndpoint;

            $postEndpoint = new EndpointItem();
            $postEndpoint->method = 'post';
            $postEndpoint->path = "/{$resources}";
            $postEndpoint->tag = $tag;
            $postEndpoint->requestEntity = $Resource;
            $postEndpoint->responseSchema = $Resource;
            if(isset($name2Method['post'])) {
                $endpoint = EndpointItem::parse($name2Method['post']);
                foreach($overrides as $override) {
                    if(isset($endpoint->{$override}))
                        $postEndpoint->{$override} = $endpoint->{$override};
                }
            }
            if(isset($item->scope) && !isset($postEndpoint->scope)) $postEndpoint->scope = $item->scope;
            if(!isset($postEndpoint->ignore)) $item->endpoints[] = $postEndpoint;

            $putByIdEndpoint = new EndpointItem();
            $putByIdEndpoint->parameters[] = $idParam;
            $putByIdEndpoint->method = 'put';
            $putByIdEndpoint->path = "/{$resources}/{id}";
            $putByIdEndpoint->tag = $tag;
            $putByIdEndpoint->requestEntity = $Resource;
            $putByIdEndpoint->responseSchema = $Resource;
            if(isset($name2Method['put'])) {
                $endpoint = EndpointItem::parse($name2Method['put']);
                foreach($overrides as $override) {
                    if(isset($endpoint->{$override}))
                        $putByIdEndpoint->{$override} = $endpoint->{$override};
                }
            }
            if(isset($item->scope) && !isset($putByIdEndpoint->scope)) $putByIdEndpoint->scope = $item->scope;
            if(!isset($putByIdEndpoint->ignore)) $item->endpoints[] = $putByIdEndpoint;

            $putEndpoint = new EndpointItem();
            $putEndpoint->method = 'put';
            $putEndpoint->path = "/{$resources}";
            $putEndpoint->tag = $tag;
            $putEndpoint->requestEntity = $Resource;
            $putEndpoint->responseSchema = $Resource;
            if(isset($name2Method['put'])) {
                $endpoint = EndpointItem::parse($name2Method['put']);
                foreach($overrides as $override) {
                    if(isset($endpoint->{$override}))
                        $putEndpoint->{$override} = $endpoint->{$override};
                }
            }
            if(isset($item->scope) && !isset($putEndpoint->scope)) $putEndpoint->scope = $item->scope;
            if(!isset($putEndpoint->ignore)) $item->endpoints[] = $putEndpoint;

            $patchByIdEndpoint = new EndpointItem();
            $patchByIdEndpoint->parameters[] = $idParam;
            $patchByIdEndpoint->method = 'patch';
            $patchByIdEndpoint->path = "/{$resources}/{id}";
            $patchByIdEndpoint->tag = $tag;
            $patchByIdEndpoint->requestEntity = $Resource;
            $patchByIdEndpoint->responseSchema = $Resource;
            $patchByIdEndpoint->isRestPatchEndpoint = true;
            if(isset($name2Method['patch'])) {
                $endpoint = EndpointItem::parse($name2Method['patch']);
                foreach($overrides as $override) {
                    if(isset($endpoint->{$override}))
                        $patchByIdEndpoint->{$override} = $endpoint->{$override};
                }
            }
            if(isset($item->scope) && !isset($patchByIdEndpoint->scope)) $patchByIdEndpoint->scope = $item->scope;
            if(!isset($patchByIdEndpoint->ignore)) $item->endpoints[] = $patchByIdEndpoint;

            $patchEndpoint = new EndpointItem();
            $patchEndpoint->method = 'patch';
            $patchEndpoint->path = "/{$resources}";
            $patchEndpoint->tag = $tag;
            $patchEndpoint->requestEntity = $Resource;
            $patchEndpoint->responseSchema = $Resource;
            if(isset($name2Method['patch'])) {
                $endpoint = EndpointItem::parse($name2Method['patch']);
                foreach($overrides as $override) {
                    if(isset($endpoint->{$override}))
                        $patchEndpoint->{$override} = $endpoint->{$override};
                }
            }
            if(isset($item->scope) && !isset($patchEndpoint->scope)) $patchEndpoint->scope = $item->scope;
            if(!isset($patchEndpoint->ignore)) $item->endpoints[] = $patchEndpoint;

            $deleteEndpoint = new EndpointItem();
            $deleteEndpoint->parameters[] = $idParam;
            $deleteEndpoint->method = 'delete';
            $deleteEndpoint->path = "/{$resources}/{id}";
            $deleteEndpoint->tag = $tag;
            $deleteEndpoint->responseSchema = $Resource;
            if(isset($name2Method['delete'])) {
                $endpoint = EndpointItem::parse($name2Method['delete']);
                foreach($overrides as $override) {
                    if(isset($endpoint->{$override}))
                        $deleteEndpoint->{$override} = $endpoint->{$override};
                }
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

                if(isset($item->scope) && !isset($endpoint->scope))
                    $endpoint->scope = $item->scope;

                if(!isset($endpoint->responseSchema) && $item->resourceNameUpperCase && $item->isResourceController)
                    $endpoint->responseSchema = $item->resourceNameUpperCase;

                $item->endpoints[] = $endpoint;
            }
        }

        if($item->isResourceController)
            $item->addImport($item->resourceNameUpperCase);

        return $item;
    }

    public function generateTypeScript(): string {
        $renderer = Services::renderer(__DIR__.'/TypeScript', null, false);
        return $renderer
            ->setData(['path' => $this, 'endpoints' => $this->endpoints], 'raw')
            ->render('Resource', ['debug' => false], null);
    }

    public function generateXamarin(): string {
        $renderer = Services::renderer(__DIR__.'/Xamarin', null, false);
        return $renderer
            ->setData(['path' => $this, 'endpoints' => $this->endpoints], 'raw')
            ->render('Resource', ['debug' => false], null);
    }

    public function generateVue(): string {
        $renderer = Services::renderer(__DIR__.'/Vue', null, false);
        return $renderer
            ->setData(['path' => $this, 'endpoints' => $this->endpoints], 'raw')
            ->render('Resource', ['debug' => false], null);
    }

    public function generateTypeScriptModelFunctions(): string {
        $renderer = Services::renderer(__DIR__.'/TypeScript', null, false);
        return $renderer
            ->setData(['apiItem' => $this], 'raw')
            ->render('ModelFunctions', ['debug' => false], null);
    }

    public function addImport($import) {
        $this->imports[$import] = $import;
    }

}
