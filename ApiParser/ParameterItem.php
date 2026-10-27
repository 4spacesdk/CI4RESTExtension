<?php namespace RestExtension\ApiParser;

use OrmExtension\ModelParser\OpenApi;

/**
 * Created by PhpStorm.
 * User: martin
 * Date: 28/11/2018
 * Time: 16.57
 *
 * @property string $name
 * @property string $type
 * @property bool $required
 * @property string $parameterType
 * @property string $default
 * @property string $example
 * @property bool $isArrayType
 * @property string $description
 */
class ParameterItem {

    public function __construct($name = '', $parameterType = 'query', $type = '', $required = true) {
        $this->name = $name;
        $this->parameterType = $parameterType;
        $this->type = $type;
        $this->isArrayType = strpos($type, '[]') !== false;
        $this->required = $required;
    }

    public static function parse($line) {
        $item = new ParameterItem();

        $parts = explode(' ', $line);
        if(count($parts) < 2) return false;

        $type = array_shift($parts);
        $name = array_shift($parts);

        $item->type = $type;
        $item->isArrayType = strpos($type, '[]') !== false;
        $item->name = substr($name, 1);

        // The text after the options, or after ` - `: `@parameter int $limit required=false - at most 100`
        $dash = array_search('-', $parts, true);
        $words = $dash === false
            ? array_filter($parts, function($part) { return strpos($part, '=') === false; })
            : array_slice($parts, $dash + 1);
        $description = trim(implode(' ', $words));
        if($description !== '') $item->description = $description;

        foreach($parts as $arg) {
            if(strpos($arg, '=') === false) continue;
            list($arg, $value) = explode('=', $arg);

            switch($arg) {
                case 'required':
                    $item->required = $value;
                    break;
                case 'default':
                    $item->default = $value;
                    break;
                case 'example':
                    $item->example = $value;
                    break;
                case 'parameterType':
                    $item->parameterType = $value;
                    break;
            }
        }

        return $item;
    }

    public function toSwagger() {
        $item = [
            'in'        => $this->parameterType,
            'name'      => $this->name,
            'required'  => $this->isRequired(),
            'schema'    => [
                'type'      => $this->type
            ]
        ];

        switch($this->type) {
            case 'int':
                $item['schema']['type'] = 'integer';
                break;
            case 'int[]':
                $item['schema']['type'] = 'string';
                break;
            case 'double':
                $item['schema']['type'] = 'number';
                break;
            case 'File':
                $item['schema'] = [
                    'type' => 'string',
                    'format' => 'binary'
                ];
                break;
        }

        if(isset($this->default)) $item['schema']['default'] = $this->default;
        if(isset($this->example)) $item['schema']['example'] = $this->example;

        return $item;
    }

    /**
     * `required=false` is the string "false", which is true to PHP.
     */
    public function isRequired(): bool {
        if($this->parameterType == 'path') return true;
        return filter_var($this->required ?? false, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Whether the parameter is in the request's body - a file, or a field of a form - which
     * OpenAPI 3 has no parameter for.
     */
    public function isBodyField(): bool {
        return !in_array($this->parameterType, ['query', 'path', 'header', 'cookie'])
            || in_array($this->type, ['File', 'File[]']);
    }

    /**
     * The parameter's schema, the default and example in it of its type.
     */
    public function getOpenApiSchema(): array {
        $schema = OpenApi::schema($this->type);
        foreach(['default', 'example'] as $key) {
            if(isset($this->{$key})) $schema[$key] = self::typed($this->{$key}, $schema);
        }
        return $schema;
    }

    /**
     * The parameter as OpenAPI 3.0 has one. A list in the query is sent as `ids=1,2,3`.
     */
    public function toOpenApi(): array {
        $item = [
            'in'        => $this->parameterType,
            'name'      => $this->name,
            'required'  => $this->isRequired(),
        ];
        if(isset($this->description)) $item['description'] = $this->description;
        $item['schema'] = $this->getOpenApiSchema();
        if(($item['schema']['type'] ?? null) == 'array') {
            $item['style'] = 'form';
            $item['explode'] = false;
        }
        return $item;
    }

    private static function typed($value, array $schema) {
        switch($schema['type'] ?? null) {
            case 'integer':
                return is_numeric($value) ? (int)$value : $value;
            case 'number':
                return is_numeric($value) ? (float)$value : $value;
            case 'boolean':
                return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $value;
            case 'array':
                return array_map(function($item) use($schema) {
                    return self::typed($item, $schema['items']);
                }, explode(',', $value));
        }
        return $value;
    }

    public function getTypeScriptType() {
        switch($this->type) {
            case 'int':
            case 'integer':
            case 'double':
                return 'number';
            case 'string|double':
                return'string';
            case 'boolean':
            case 'bool':
                return 'boolean';
            case 'string':
                return 'string';
            case 'int[]':
                return 'number[]';
            default:
                return $this->type;
        }
    }

    public function getXamarinType() {
        switch($this->type) {
            case 'int':
            case 'integer':
                return 'int';
            case 'double':
                return 'double';
            case 'string|double':
                return'string';
            case 'boolean':
            case 'bool':
                return 'bool';
            case 'string':
                return 'string';
            case 'int[]':
                return 'int[]';
            case 'double[]':
                return 'double[]';
            default:
                return $this->type;
        }
    }

}
