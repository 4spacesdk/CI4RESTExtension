<?php namespace RestExtension\ApiParser;

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
 */
class EndpointItem {

    public $method = 'get';
    public $parameters = [];
    public $custom = false;
    public $requestBodyType = 'application/json';

    /**
     * @param \ReflectionMethod $line
     * @return bool|array
     */
    public static function validate($line) {
        $comments = explode("\n", $line->getDocComment());
        foreach($comments as $comment) {
            if(strpos($comment, "@route") !== false)
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
        if(!$comments) $comments = $comments = explode("\n", $method->getDocComment());

        $item = new EndpointItem();
        $item->methodName = $method->name;

        foreach($comments as $comment) {

            if(strpos($comment, "@ignore") !== false)
                $item->ignore = true;

            $line = substr($comment, 7);
            $parts = explode(' ', $line);
            if(count($parts) < 2) continue;

            $field = $parts[0];
            $value = $parts[1];
            switch($field) {
                case '@route':
                    $item->path = $value;
                    break;
                case '@method':
                    $item->method = $value;
                    break;
                case '@param':
                    $param = ParameterItem::parse(substr($line, strlen('@param ')));
                    if($param) {
                        $param->parameterType = 'path';
                        $item->parameters[] = $param;
                    }
                    break;
                case '@parameter':
                    $param = ParameterItem::parse(substr($line, strlen('@parameter ')));
                    if($param)
                        $item->parameters[] = $param;
                    break;
                case '@custom':
                    $item->custom = true;
                    break;
                case '@requestSchema':
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
                    break;
                case '@requestBodyType':
                    $item->requestBodyType = $value;
                    break;
            }
        }

        $className = $method->getDeclaringClass()->getName();
        $Resource = substr($className, strrpos($className, '\\') + 1); // Remove namespace
        $item->tag = $Resource;

        return $item;
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
        if(isset($this->responseSchema)) {
            if(strpos($this->responseSchema, '[]') !== false) {
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
        foreach($this->parameters as $parameter)
            $item['parameters'][] = $parameter->toSwagger();

        if(isset($this->requestEntity))
            $item['requestBody'] = [
                'content' => [
                    $this->requestBodyType => [
                        'schema' => [
                            '$ref' => '#/components/schemas/'.$this->requestEntity
                        ]
                    ]
                ]
            ];

        if(isset($this->summary))
            $item['summary'] = $this->summary;

        if(isset($this->scope)) {
            $item['security'] = [
                [
                    'OAuth2' => explode(' ', $this->scope)
                ]
            ];
            //if(!isset($item['summary'])) $item['summary'] = '';
            //$item['summary'] .= ' Scope: '.$this->scope;
        }

        return $item;
    }

    public function getTypeScriptPathArgumentsWithTypes(): array {
        $argsWithType = [];
        foreach($this->parameters as $parameter) {
            if($parameter->parameterType == 'path') {
                $required = $parameter->required ? '' : '?';
                $argsWithType[] = "{$parameter->name}{$required}: {$parameter->getTypeScriptType()}";
            }
        }
        return $argsWithType;
    }

    public function getTypeScriptPathArgumentsWithOutTypes(): array {
        $argsWithOutType = [];
        foreach($this->parameters as $parameter) {
            if($parameter->parameterType == 'path') {
                $argsWithOutType[] = $parameter->name;
            }
        }
        return $argsWithOutType;
    }

    public function getTypeScriptUrl(): string {
        $params = $this->getTypeScriptPathArgumentsWithOutTypes();
        $url = $this->path;
        foreach($params as $param) {
            $url = str_replace("{{$param}}", "\${{$param}}", $url);
        }
        return $url;
    }

    /**
     * @return ParameterItem[]
     */
    public function getTypeScriptQueryParameters(): array {
        $ignore = ['filter', 'include', 'ordering', 'limit', 'offset', 'count'];
        $parameters = [];
        foreach($this->parameters as $parameter) {
            if($parameter->parameterType == 'query' && !in_array($parameter->name, $ignore)) {
                $parameters[] = $parameter;
            }
        }
        return $parameters;
    }

    public function hasParameter($name): bool {
        foreach($this->parameters as $parameter) {
            if($parameter->name == $name)
                return true;
        }
        return false;
    }

}
