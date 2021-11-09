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
 * @property string[] $schemaReferences
 * @property InterfaceItem[] $interfaces
 */
class ApiParser {

    /**
     * @param string $scope
     * @return ApiParser
     * @throws \ReflectionException
     */
    public static function run($scope = null) {
        $parser = new ApiParser();

        $interfaces = [];
        foreach(ApiParser::loadInterfaces() as $interface) {
            $interfaceItem = InterfaceItem::parse(substr($interface, 0, -4));
            if($interfaceItem) {
                $interfaces[] = $interfaceItem;
            }
        }
        $parser->interfaces = $interfaces;

        $apis = [];
        $parser->schemaReferences = [];
        foreach(ApiParser::loadApi() as $api) {
            $apiItem = ApiParser::parseApiItem($api);

            if($scope) {
                $apiItem->endpoints = array_filter($apiItem->endpoints, function(EndpointItem $endpoint) use($scope) {
                    return !isset($endpoint->scope) || strpos($endpoint->scope, $scope) !== false;
                });
            }

            if(count($apiItem->endpoints) == 0)
                continue;

            $apis[] = $apiItem;

            foreach($apiItem->endpoints as $endpoint) {
                if(isset($endpoint->requestEntity)) {
                    if(!in_array($endpoint->requestEntity, $parser->schemaReferences))
                        $parser->schemaReferences[] = $endpoint->requestEntity;
                }
                if(isset($endpoint->responseSchema)) {
                    $schemaReference = trim($endpoint->responseSchema, '[]');
                    if(!in_array($schemaReference, $parser->schemaReferences))
                        $parser->schemaReferences[] = $schemaReference;
                }
            }
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

        $imports = [];
        foreach($this->paths as $path) {
            foreach($path->endpoints as $endpoint) {
                if($endpoint->isResponseSchemaAModel()) $path->addImport($endpoint->getBaseResponseSchemaName());
                if($endpoint->isRequestSchemaAModel()) $path->addImport($endpoint->getBaseRequestSchemaName());
            }

            $imports = array_merge($imports, $path->imports);
        }

        $content = $renderer->setData([
            'imports' => $imports,
            'resources' => $this->paths,
            'interfaces' => $this->interfaces
        ], 'raw')->render('API', ['debug' => false], null);
        if($debug) {
            header('Content-Type', 'text/plain');
            echo $content;
            exit(0);
        } else
            file_put_contents(WRITEPATH.'tmp/Api.ts', $content);
    }

    public function generateVue($debug) {
        if(!file_exists(WRITEPATH.'tmp')) mkdir(WRITEPATH.'tmp', 0777, true);

        $renderer = Services::renderer(__DIR__.'/Vue', null, false);

        $imports = [];
        foreach($this->paths as $path) {
            foreach($path->endpoints as $endpoint) {
                if($endpoint->isResponseSchemaAModel()) $path->addImport($endpoint->getBaseResponseSchemaName());
                if($endpoint->isRequestSchemaAModel()) $path->addImport($endpoint->getBaseRequestSchemaName());
            }

            $imports = array_merge($imports, $path->imports);
        }

        foreach ($this->interfaces as $interface) {
            foreach ($interface->properties as $property) {
                if (!$property->isSimpleType && !$property->isInterface) {
                    $type = str_replace('[]', '', $property->rawType);
                    if (!in_array($type, $imports)) {
                        $imports[] = $type;
                    }
                }
            }
        }

        $content = $renderer->setData([
            'imports' => $imports,
            'resources' => $this->paths,
            'interfaces' => $this->interfaces
        ], 'raw')->render('API', ['debug' => false], null);
        if($debug) {
            header('Content-Type', 'text/plain');
            echo $content;
            exit(0);
        } else
            file_put_contents(WRITEPATH.'tmp/Api.ts', $content);
    }

    public function generateXamarin($debug) {
        if(!file_exists(WRITEPATH.'tmp')) mkdir(WRITEPATH.'tmp', 0777, true);

        $renderer = Services::renderer(__DIR__.'/Xamarin', null, false);

        $imports = [];
        foreach($this->paths as $path) {
            foreach($path->endpoints as $endpoint) {
                if($endpoint->isResponseSchemaAModel()) $path->addImport($endpoint->responseSchema);
                if($endpoint->isRequestSchemaAModel()) $path->addImport($endpoint->requestEntity);
            }

            $imports = array_merge($imports, $path->imports);
        }

        $content = $renderer->setData([
            'imports' => $imports,
            'resources' => $this->paths,
            'interfaces' => $this->interfaces
        ], 'raw')->render('API', ['debug' => false], null);
        if($debug) {
            header('Content-Type', 'text/plain');
            echo $content;
            exit(0);
        } else
            file_put_contents(WRITEPATH.'tmp/Api.cs', $content);
    }


    /**
     * @param $api
     * @return ApiItem
     * @throws \ReflectionException
     */
    private static function parseApiItem($api) {
        return ApiItem::parse(substr($api, 0, -4));
    }

    private static function loadApi(): array {
        return self::loadApiDirectory(APPPATH . 'Controllers');
    }

    private static function loadApiDirectory(string $base, string $append = DIRECTORY_SEPARATOR): array {
        $files = scandir($base.$append);
        $apis = [];
        foreach($files as $file) {
            if (is_dir($base . $append . $file) && $file != '..' && $file != '.') {
                $apis = array_merge($apis, self::loadApiDirectory($base, $append . $file . DIRECTORY_SEPARATOR));
            }
            if($file[0] != '_' && substr($file, -3) == 'php') {
                $apis[] = str_replace('/', '\\', $append . $file);
            }
        }
        return $apis;
    }

    private static function loadInterfaces() {
        if(!is_dir(APPPATH. 'Interfaces')) return [];

        $files = scandir(APPPATH . 'Interfaces');
        $apis = [];
        foreach($files as $file) {
            if($file[0] != '_' && substr($file, -3) == 'php') {
                $apis[] = $file;
            }
        }
        $apiIgnore = [];
        $apis = array_diff($apis, $apiIgnore);
        return $apis;
    }

}
