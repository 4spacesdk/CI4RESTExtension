<?php namespace RestExtension\ApiParser;

use CodeIgniter\Config\Config;
use Config\OrmExtension;
use Config\RestExtension;
use Config\Services;

/**
 * Created by PhpStorm.
 * User: martin
 * Date: 25/11/2018
 * Time: 12.20
 *
 * @property string $path
 * @property string $methodName
 * @property string $method
 * @property string $tag
 * @property ParameterItem[] $parameters
 * @property string $requestEntity
 * @property string $requestBodyType
 * @property string $summary
 * @property string $scope
 * @property bool $ignore
 * @property string $responseSchema
 * @property string $security
 * @property InterfaceItem $responseInterfaceItem
 * @property InterfaceItem $requestInterfaceItem
 * @property boolean $isRestPatchEndpoint
 */
class EndpointItem {

    public $method = 'get';
    public $parameters = [];
    public $custom = false;
    public $requestBodyType = 'application/json';
    public $security = 'OAuth2';
    public $isRestPatchEndpoint = false;

    /**
     * @param \ReflectionMethod $line
     * @return bool|array
     */
    public static function validate($line) {
        $comments = explode("\n", $line->getDocComment());
        foreach ($comments as $comment) {
            if (strpos($comment, "@route") !== false)
                return $comments;
        }
        return false;
    }

    /**
     * @param \ReflectionMethod $method
     * @param $comments
     * @return EndpointItem|bool
     */
    public static function parse($method, $comments = null) {
        if (!$comments) $comments = $comments = explode("\n", $method->getDocComment());

        $item = new EndpointItem();
        $item->methodName = $method->name;

        /** @var RestExtension $config */
        $config = Config::get('RestExtension');

        foreach ($comments as $comment) {

            if (strpos($comment, "@ignore") !== false)
                $item->ignore = true;

            $line = substr($comment, 7);
            $parts = explode(' ', $line);
            if (count($parts) < 2) continue;

            $field = $parts[0];
            $value = $parts[1];
            switch ($field) {
                case '@route':
                    $item->path = $value;
                    break;
                case '@method':
                    $item->method = $value;
                    break;
                case '@param':
                    $param = ParameterItem::parse(substr($line, strlen('@param ')));
                    if ($param) {
                        $param->parameterType = 'path';
                        $item->parameters[] = $param;
                    }
                    break;
                case '@parameter':
                    $param = ParameterItem::parse(substr($line, strlen('@parameter ')));
                    if ($param)
                        $item->parameters[] = $param;
                    break;
                case '@custom':
                    $item->custom = true;
                    break;
                case '@requestSchema':
                    try {
                        $item->requestInterfaceItem = InterfaceItem::parse($value);
                    } catch (\ReflectionException $e) {
                    }
                case '@entity':
                    $item->requestEntity = $value;
                    break;
                case '@summary':
                    $item->summary = implode(' ', array_splice($parts, 1));
                    break;
                case '@scope':
                    $item->scope = implode(' ', array_splice($parts, 1));
                    break;
                case '@responseSchema':
                    $item->responseSchema = $value;
                    try {
                        $item->responseInterfaceItem = InterfaceItem::parse($value);
                    } catch (\ReflectionException $e) {
                    }
                    break;
                case '@requestBodyType':
                    $item->requestBodyType = $value;
                    break;
                case '@security':
                    $item->security = $value;
                    break;
            }
        }

        $className = $method->getDeclaringClass()->getName();
        $item->tag = str_replace([$config->apiControllerNamespace, '\\'], '', $className);

        return $item;
    }

    public function getResponseInterfaceItem() {
        if (isset($this->responseInterfaceItem))
            return $this->responseInterfaceItem;
        else if (isset($this->responseSchema)) {
            try {
                $this->responseInterfaceItem = InterfaceItem::parse($this->responseSchema);
                return $this->responseInterfaceItem;
            } catch (\ReflectionException $e) {
            }
        }
        return null;
    }

    public function getRequestInterfaceItem() {
        if (isset($this->requestInterfaceItem))
            return $this->requestInterfaceItem;
        else if (isset($this->requestEntity)) {
            try {
                $this->requestInterfaceItem = InterfaceItem::parse($this->requestEntity);
                return $this->requestInterfaceItem;
            } catch (\ReflectionException $e) {
            }
        }
        return null;
    }

    public function getXamarinResponseSchema(): string {
        if (isset($this->responseInterfaceItem)) {
            return $this->responseSchema;
        } else if (isset($this->responseSchema)) {
            return Config::get('OrmExtension')->xamarinModelsNamespace . '.' . $this->responseSchema;
        } else {
            return 'Empty';
        }
    }

    public function toSwagger() {
        $item = [
            'tags' => [
                $this->tag
            ],
            "responses" => [
                "200" => [
                    "description" => "Success"
                ]
            ]
        ];
        if (isset($this->responseSchema)) {
            if (strpos($this->responseSchema, '[]') !== false) {
                $resources = [
                    'resources' => [
                        'type' => 'array',
                        'items' => [
                            '$ref' => '#/components/schemas/' . substr($this->responseSchema, 0, -2)
                        ]
                    ]
                ];
            } else {
                $resources = [
                    'resource' => [
                        '$ref' => '#/components/schemas/' . $this->responseSchema
                    ]
                ];
            }
            $item['responses']['200']['content'] = [
                'application/json' => [
                    'schema' => [
                        'type' => 'object',
                        'properties' => array_merge(['status' => [
                            'type' => 'string',
                            'description' => 'OK'
                        ]], $resources)
                    ]
                ]
            ];
        }
        $item['parameters'] = [];
        foreach ($this->parameters as $parameter)
            $item['parameters'][] = $parameter->toSwagger();

        if (isset($this->requestEntity))
            $item['requestBody'] = [
                'content' => [
                    $this->requestBodyType => [
                        'schema' => [
                            '$ref' => '#/components/schemas/' . $this->requestEntity
                        ]
                    ]
                ]
            ];

        if (isset($this->summary))
            $item['summary'] = $this->summary;

        if (isset($this->scope)) {
            $item['security'] = [
                [
                    $this->security => explode(' ', $this->scope)
                ]
            ];
            //if(!isset($item['summary'])) $item['summary'] = '';
            //$item['summary'] .= ' Scope: '.$this->scope;
        }

        return $item;
    }

    public function generateTypeScript(): string {
        $renderer = Services::renderer(__DIR__ . '/TypeScript', null, false);
        return $renderer
            ->setData(['endpoint' => $this], 'raw')
            ->render('Endpoint', ['debug' => false], null);
    }

    public function generateXamarin(): string {
        $renderer = Services::renderer(__DIR__ . '/Xamarin', null, false);
        return $renderer
            ->setData(['endpoint' => $this], 'raw')
            ->render('Endpoint', ['debug' => false], null);
    }

    public function generateVue(): string {
        $renderer = Services::renderer(__DIR__ . '/Vue', null, false);
        return $renderer
            ->setData(['endpoint' => $this], 'raw')
            ->render('Endpoint', ['debug' => false], null);
    }

    public function getTypeScriptPathArgumentsWithTypes(): array {
        $argsWithType = [];
        foreach ($this->parameters as $parameter) {
            if ($parameter->parameterType == 'path') {
                $required = $parameter->required ? '' : '?';
                $argsWithType[] = "{$parameter->name}{$required}: {$parameter->getTypeScriptType()}";
            }
        }
        return $argsWithType;
    }

    public function getXamarinPathArgumentsWithTypes(): array {
        $argsWithType = [];
        foreach ($this->parameters as $parameter) {
            if ($parameter->parameterType == 'path') {
                $required = $parameter->required ? '' : '?';
                $argsWithType[] = "{$parameter->getXamarinType()} {$parameter->name}{$required}";
            }
        }
        return $argsWithType;
    }

    public function getTypeScriptPathArgumentsWithOutTypes(): array {
        $argsWithOutType = [];
        foreach ($this->parameters as $parameter) {
            if ($parameter->parameterType == 'path') {
                $argsWithOutType[] = $parameter->name;
            }
        }
        return $argsWithOutType;
    }

    public function getTypeScriptUrl(): string {
        $params = $this->getTypeScriptPathArgumentsWithOutTypes();
        $url = $this->path;
        foreach ($params as $param) {
            $url = str_replace("{{$param}}", "\${{$param}}", $url);
        }
        return $url;
    }

    public function getXamarinUrl(): string {
        $params = $this->getTypeScriptPathArgumentsWithOutTypes();
        $url = $this->path;
        $counter = 0;
        foreach ($params as $param) {
            $url = str_replace("{{$param}}", "{{$counter}}", $url);
            $counter++;
        }
        return $url;
    }

    /**
     * @return ParameterItem[]
     */
    public function getTypeScriptQueryParameters(): array {
        $ignore = ['filter', 'include', 'ordering', 'limit', 'offset', 'count'];
        $parameters = [];
        foreach ($this->parameters as $parameter) {
            if ($parameter->parameterType == 'query' && !in_array($parameter->name, $ignore)) {
                $parameters[] = $parameter;
            }
        }
        return $parameters;
    }

    public function getTypeScriptFunctionName(): string {
        // Append path arguments to function name, to ensure uniqueness
        $with = [];
        foreach ($this->getTypeScriptPathArgumentsWithOutTypes() as $pathArgument)
            $with[] = ucfirst($pathArgument);
        $by = count($with) ? 'By' . implode('By', $with) : '';
        $funcName = lcfirst($this->method) . $by;

        if ($this->custom) {
            $customName = $this->methodName ?? '';
            $customName = str_replace(['_get', '_put', '_delete', '_patch'], '', $customName);

            $funcName = $customName . ucfirst($funcName);
        }

        return $funcName;
    }

    public function getTypeScriptClassName(): string {
        // Append path arguments to class name, to ensure uniqueness
        $with = [];
        foreach ($this->getTypeScriptPathArgumentsWithOutTypes() as $pathArgument)
            $with[] = ucfirst($pathArgument);
        $by = count($with) ? 'By' . implode('By', $with) : '';

        $name = $this->tag;
        if ($this->custom) {
            $customName = $this->methodName ?? '';
            $customName = str_replace(['_get', '_put', '_delete', '_patch'], '', $customName);
            $name .= ucfirst($customName);
        }

        $className = ucfirst($name) . ucfirst($this->method) . $by;
        return $className;
    }

    public function getTopicName(): string {
        if (isset($this->responseSchema)) {
            if (strpos($this->responseSchema, '[]') !== false) {
                return "Resources." . plural(substr($this->responseSchema, 0, -2));
            } else {
                return "Resources." . plural($this->responseSchema);
            }
        } else
            return "UnknownResource";
    }

    public function hasParameter($name): bool {
        foreach ($this->parameters as $parameter) {
            if ($parameter->name == $name)
                return true;
        }
        return false;
    }

    public function getBaseResponseSchemaName(): string {
        if (strpos($this->responseSchema, '[]') !== false) {
            return substr($this->responseSchema, 0, -2);
        } else {
            return $this->responseSchema;
        }
    }

    public function getBaseRequestSchemaName(): string {
        if (strpos($this->requestEntity, '[]') !== false) {
            return substr($this->requestEntity, 0, -2);
        } else {
            return $this->requestEntity;
        }
    }

    public function isResponseSchemaAModel(): bool {
        if (!isset($this->responseSchema)) {
            return false;
        }
        foreach (OrmExtension::$entityNamespace as $namespace) {
            if (class_exists($namespace . $this->getBaseResponseSchemaName())) {
                return true;
            }
        }
        return false;
    }

    public function isRequestSchemaAModel(): bool {
        if (!isset($this->requestEntity)) return false;
        foreach (OrmExtension::$entityNamespace as $namespace) {
            if (class_exists($namespace . $this->getBaseRequestSchemaName()))
                return true;
        }
        return false;
    }

}
