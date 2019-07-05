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
     * @return ApiParser
     * @throws \ReflectionException
     */
    public static function run() {
        $parser = new ApiParser();
        $apis = [];
        foreach(ApiParser::loadApi() as $api) {
            $apis[] = ApiParser::parseApiItem($api);
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
                $className = $path->name.ucfirst($endpoint->method);
                $endpoints[$className] = $renderer
                    ->setData(['endpoint' => $endpoint], 'raw')
                    ->render('Endpoint', ['debug' => false], null);
            }

            $resources[] = $renderer
                ->setData(['path' => $path, 'endpoints' => $endpoints], 'raw')
                ->render('Resource', ['debug' => false], null);
        }

        $content = $renderer->setData(['resources' => $resources], 'raw')->render('API', ['debug' => false], null);
        if($debug) {
            echo str_replace(" ", '&nbsp;', str_replace("\n", '<br>', $content));
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
