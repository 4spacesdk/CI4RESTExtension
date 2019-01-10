<?php namespace RestExtension\ApiParser;
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