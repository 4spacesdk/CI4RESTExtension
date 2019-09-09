<?php namespace RestExtension\ApiParser;

use CodeIgniter\Config\Config;
use Config\Services;

/**
 * Class InterfaceItem
 * @package RestExtension\ApiParser\TypeScript
 * @property string $path
 * @property string $name
 * @property PropertyItem[] $properties
 */
class InterfaceItem {

    public $properties = [];

    /**
     * @param $path
     * @return InterfaceItem
     * @throws \ReflectionException
     */
    public static function parse($path) {
        $item = new InterfaceItem();
        $item->path = $path;

        $namespace = Config::get('RestExtension')->apiInterfaceNamespace;
        $class = "$namespace\\{$path}";

        $rc = new \ReflectionClass($class);
        $item->name = substr($rc->getName(), strrpos($rc->getName(), '\\') + 1);

        $comments = $rc->getDocComment();
        $lines = explode("\n", $comments);
        foreach($lines as $line) {
            $property = PropertyItem::parse($line);
            if($property)
                $item->properties[] = $property;
        }

        return $item;
    }

    public function toSwagger() {
        $item = [
            'title'         => $this->name,
            'type'          => 'object',
            'properties'    => []
        ];
        foreach($this->properties as $property) {
            $item['properties'][$property->name] = $property->toSwagger();
        }
        return $item;
    }

    public function toTypeScript() {
        $renderer = Services::renderer(__DIR__.'/TypeScript', null, false);
        return $renderer
            ->setData(['interfaceItem' =>  $this], 'raw')
            ->render('Interface', ['debug' => false], null);
    }

}
