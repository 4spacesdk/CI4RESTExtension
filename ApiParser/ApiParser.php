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
            $interfaceItem = InterfaceItem::parse($interface);
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

        // Models named by an interface property, which no endpoint imports on its own behalf
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
            'interfaces' => $this->interfaces,
            'baseApiImportPath' => self::typescriptOption('typescriptBaseApiImportPath', '@app/core/http/Api/BaseApi'),
            'modelsImportPath' => self::typescriptOption('typescriptModelsImportPath', '@app/core/models'),
        ], 'raw')->render('API', ['debug' => false], null);
        if($debug) {
            header('Content-Type', 'text/plain');
            echo $content;
            exit(0);
        } else
            file_put_contents(WRITEPATH.'tmp/Api.ts', $content);
    }

    /**
     * A TypeScript export option from Config\RestExtension. The application's config class
     * only has the options it was written with, so one added later falls back to its default.
     */
    public static function typescriptOption(string $name, $default) {
        $config = config('RestExtension');
        return $config && isset($config->{$name}) ? $config->{$name} : $default;
    }

    /**
     * Writes BaseApi.ts and the filter, include and ordering classes it uses into $directory,
     * replacing what is there. Api.ts extends BaseApi, so they go next to it.
     */
    public static function writeTypeScriptBaseClasses(string $directory): void {
        if (!is_dir($directory)) mkdir($directory, 0777, true);
        foreach (['BaseApi.ts', 'ApiFilter.ts', 'ApiInclude.ts', 'ApiOrdering.ts'] as $file) {
            copy(__DIR__ . '/TypeScript/BaseClasses/' . $file, rtrim($directory, '/') . '/' . $file);
        }
    }

    /**
     * Writes BaseModel.ts into $directory, replacing what is there. The model definitions
     * import it from their parent folder, so it goes next to index.ts.
     */
    public static function writeTypeScriptBaseModel(string $directory): void {
        if (!is_dir($directory)) mkdir($directory, 0777, true);
        copy(__DIR__ . '/TypeScript/BaseClasses/BaseModel.ts', rtrim($directory, '/') . '/BaseModel.ts');
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
     * @param string $api a controller's full class name
     * @return ApiItem
     * @throws \ReflectionException
     */
    private static function parseApiItem($api) {
        return ApiItem::parse($api);
    }

    /**
     * Every controller of every controller namespace (Sources).
     */
    private static function loadApi(): array {
        return Sources::controllers();
    }

    /**
     * Every interface of every interface namespace (Sources).
     */
    private static function loadInterfaces() {
        return Sources::interfaces();
    }

}
