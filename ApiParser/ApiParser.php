<?php namespace RestExtension\ApiParser;
use Config\Services;
use DebugTool\Data;

/**
 * Created by PhpStorm.
 * User: martin
 * Date: 27/11/2018
 * Time: 08.33
 *
 * @property ApiItem[] $paths
 */
class ApiParser {

    /**
     * @param string $scope
     * @return ApiParser
     * @throws \ReflectionException
     */
    public static function run($scope = null) {
        $parser = new ApiParser();
        $apis = [];
        foreach(ApiParser::loadApi() as $api) {
            $apiItem = ApiParser::parseApiItem($api);
            if(count($apiItem->endpoints) == 0)
                continue;
            if($scope && isset($apiItem->scope) && strpos($apiItem->scope, $scope) === false) {
                continue;
            }
            $apis[] = $apiItem;
        }
        Data::debug("Found ".count($apis)." apis");
        $parser->paths = $apis;
        return $parser;
    }

    public function generateSwagger() {
        $json = [];
        foreach($this->paths as $path) {
            foreach($path->endpoints as $endpoint) {
                $json[$endpoint->path][$endpoint->method] = $endpoint->toSwagger();
            }
        }
        return $json;
    }

    public function generateTypeScript($debug) {
        if(!file_exists(WRITEPATH.'tmp')) mkdir(WRITEPATH.'tmp', 0777, true);

        $renderer = Services::renderer(__DIR__.'/TypeScript', null, false);

        $resources = [];
        foreach($this->paths as $path) {
            $endpoints = [];
            foreach($path->endpoints as $endpoint) {
                $parts = explode('/', trim($endpoint->path, '/'));

                // Name
                $customName = array_shift($parts);
                $name = $endpoint->tag;
                if($endpoint->custom) $name .= ucfirst($customName);

                // Path parameters
                $with = [];
                foreach($endpoint->getTypeScriptPathArgumentsWithOutTypes() as $pathArgument)
                    $with[] = ucfirst($pathArgument);
                $by = count($with) ? 'By'.implode('By', $with) : '';

                // Func & Class name
                $funcName = lcfirst($endpoint->method).$by;
                if($endpoint->custom)
                    $funcName = $customName.ucfirst($funcName);
                $className = ucfirst($name).ucfirst($endpoint->method).$by;

                // Function Arguments
                $argsWithType = $endpoint->getTypeScriptPathArgumentsWithTypes();
                $argsWithOutType = $endpoint->getTypeScriptPathArgumentsWithOutTypes();

                $endpoints[] = [$funcName, $className, implode(', ', $argsWithType), implode(', ', $argsWithOutType), $renderer
                        ->setData(['endpoint' => $endpoint, 'className' => $className, 'apiItem' => $path], 'raw')
                        ->render('Endpoint', ['debug' => false], null),
                ];
            }

            $resources[$path->name] = $renderer
                ->setData(['path' => $path, 'endpoints' => $endpoints], 'raw')
                ->render('Resource', ['debug' => false], null);
        }

        $content = $renderer->setData(['resources' => $resources], 'raw')->render('API', ['debug' => false], null);
        if($debug) {
            header('Content-Type', 'text/plain');
            echo $content;
            exit(0);
        } else
            file_put_contents(WRITEPATH.'tmp/Api.ts', $content);
    }


    /**
     * @param $api
     * @return ApiItem
     * @throws \ReflectionException
     */
    private static function parseApiItem($api) {
        return ApiItem::parse(substr($api, 0, -4));
    }

    private static function loadApi() {
        $files = scandir(APPPATH . 'Controllers');
        $apis = [];
        foreach($files as $file) {
            if($file[0] != '_' && substr($file, -3) == 'php') {
                $apis[] = $file;
            }
        }
        $apiIgnore = ['_template.php', 'Home.php'];
        $apis = array_diff($apis, $apiIgnore);
        return $apis;
    }

}
