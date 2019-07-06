<?php namespace RestExtension\ApiParser;

/**
 * Created by PhpStorm.
 * User: martin
 * Date: 25/11/2018
 * Time: 12.20
 *
 * @property string $path
 * @property string $method
 * @property string $tag
 * @property ParameterItem[] $parameters
 * @property string $requestEntity
 * @property string $summary
 * @property string $scope
 */
class EndpointItem {

    public $method = 'get';
    public $parameters = [];
    public $custom = false;

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
     * @return EndpointItem|bool
     */
    public static function parse($method) {
        $comments = EndpointItem::validate($method);
        if(!$comments) return false;

        $item = new EndpointItem();

        foreach($comments as $comment) {
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
                case '@entity':
                    $item->requestEntity = $value;
                    break;
                case '@summary':
                    $item->summary = implode(' ', array_splice($parts, 1));
                    break;
                case '@scope':
                    $item->scope = implode(' ', array_splice($parts, 1));
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
                    "description" => ""
                ]
            ]
        ];
        $item['parameters'] = [];
        foreach($this->parameters as $parameter)
            $item['parameters'][] = $parameter->toSwagger();

        if(isset($this->requestEntity))
            $item['requestBody'] = [
                'content' => [
                    'application/json' => [
                        'schema' => [
                            '$ref' => '#/components/schemas/'.$this->requestEntity
                        ]
                    ]
                ]
            ];

        if(isset($this->summary))
            $item['summary'] = $this->summary;

        if(isset($this->scope))
            $item['security'] = [
                [
                    'OAuth2' => explode(' ', $this->scope)
                ]
            ];

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
