<?php namespace RestExtension\ApiParser;

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
            'required'  => (bool)$this->required || $this->parameterType == 'path',
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

}
