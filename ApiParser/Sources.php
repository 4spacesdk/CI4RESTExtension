<?php namespace RestExtension\ApiParser;

use OrmExtension\ModelParser\Namespaces;

/**
 * Where the API export finds controllers and interfaces: every namespace in
 * `Config\RestExtension::$apiControllerNamespace` and `$apiInterfaceNamespace`, each a string or a
 * list, so the endpoints of a composer package are exported with the app's. A namespace's
 * directories come from CodeIgniter's autoloader.
 */
class Sources {

    /**
     * @return string[]
     */
    public static function controllerNamespaces() {
        return self::namespaces('apiControllerNamespace', 'App\Controllers');
    }

    /**
     * @return string[]
     */
    public static function interfaceNamespaces() {
        return self::namespaces('apiInterfaceNamespace', 'App\Interfaces');
    }

    /**
     * Every controller, as a full class name, in subdirectories too. A controller by the same
     * relative name in two namespaces is the first namespace's.
     *
     * @return string[]
     */
    public static function controllers() {
        $classes = [];
        foreach (self::controllerNamespaces() as $namespace) {
            foreach (Namespaces::directories($namespace) as $directory) {
                foreach (self::scan($directory, '') as $relative) {
                    if (!isset($classes[$relative])) {
                        $classes[$relative] = $namespace . '\\' . $relative;
                    }
                }
            }
        }
        return array_values($classes);
    }

    /**
     * Every interface, as a full class name.
     *
     * @return string[]
     */
    public static function interfaces() {
        return Namespaces::classes(self::interfaceNamespaces());
    }

    /**
     * A controller's full class name: as it is when it is one, or in the first namespace.
     */
    public static function controllerClass($api) {
        $api = trim($api, '\\');
        if (class_exists($api)) {
            return $api;
        }
        foreach (self::controllerNamespaces() as $namespace) {
            if (class_exists($namespace . '\\' . $api)) {
                return $namespace . '\\' . $api;
            }
        }
        return self::controllerNamespaces()[0] . '\\' . $api;
    }

    /**
     * A controller's name without its namespace, as the export names its tag: Admin\Users is
     * AdminUsers.
     */
    public static function relativeName($className) {
        $className = trim($className, '\\');
        $best = '';
        foreach (self::controllerNamespaces() as $namespace) {
            if (strpos($className, $namespace . '\\') === 0 && strlen($namespace) > strlen($best)) {
                $best = $namespace;
            }
        }
        return str_replace('\\', '', $best === '' ? $className : substr($className, strlen($best) + 1));
    }

    /**
     * The full class name of an interface by its short name, or null when there is none.
     */
    public static function interfaceClass($name) {
        foreach (self::interfaceNamespaces() as $namespace) {
            if (interface_exists($namespace . '\\' . $name)) {
                return $namespace . '\\' . $name;
            }
        }
        return null;
    }

    /**
     * Whether a controller is a resource controller: one whose parent is a class in
     * `Config\RestExtension::$resourceControllerClass` (a string or a list), App\Core\ResourceController
     * unless it says otherwise.
     */
    public static function isResourceController(\ReflectionClass $rc) {
        $parent = $rc->getParentClass();
        if (!$parent) {
            return false;
        }
        $classes = self::namespaces('resourceControllerClass', 'App\Core\ResourceController');
        return in_array(trim($parent->getName(), '\\'), $classes, true);
    }

    /**
     * @return string[]
     */
    private static function namespaces($property, $default) {
        $config = config('RestExtension');
        $value = isset($config->$property) && $config->$property !== '' ? $config->$property : $default;
        return array_values(array_map(function ($namespace) {
            return trim($namespace, '\\');
        }, is_array($value) ? $value : [$value]));
    }

    /**
     * @return string[] relative class names, App\Controllers\Admin\Users as Admin\Users
     */
    private static function scan($directory, $prefix) {
        $classes = [];
        foreach (scandir($directory) as $file) {
            if ($file == '.' || $file == '..') {
                continue;
            }
            if (is_dir($directory . DIRECTORY_SEPARATOR . $file)) {
                $classes = array_merge($classes, self::scan($directory . DIRECTORY_SEPARATOR . $file, $prefix . $file . '\\'));
            } else if ($file[0] != '_' && substr($file, -4) == '.php') {
                $classes[] = $prefix . substr($file, 0, -4);
            }
        }
        return $classes;
    }

}
