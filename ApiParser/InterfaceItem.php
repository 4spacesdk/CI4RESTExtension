<?php namespace RestExtension\ApiParser;

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

        $config = config('RestExtension');
        $namespace = $config->apiInterfaceNamespace ?? '';
        $class = "$namespace\\{$path}";

        try {
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
        } catch(\Exception $e) {

        }

        return null;
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

    public function toVue() {
        $renderer = Services::renderer(__DIR__.'/Vue', null, false);
        return $renderer
            ->setData(['interfaceItem' =>  $this], 'raw')
            ->render('Interface', ['debug' => false], null);
    }

    public function generateXamarin() {
        $renderer = Services::renderer(__DIR__.'/Xamarin', null, false);
        return $renderer
            ->setData(['interfaceItem' =>  $this], 'raw')
            ->render('Interface', ['debug' => false], null);
    }

}
