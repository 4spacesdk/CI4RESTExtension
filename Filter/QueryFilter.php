<?php namespace RestExtension\Filter;

/**
 * Created by PhpStorm.
 * User: martin
 * Date: 2018-12-04
 * Time: 13:05
 *
 * @property string $property
 * @property string $operator
 * @property string|array $value
 */
class QueryFilter {

    public static function parse($line) {
        $item = new QueryFilter();

        $item->property = substr($line, 0, strpos($line, ':'));
        $item->operator = Operators::Equal;

        $value = substr($line, strlen($item->property) + 1);
        foreach(Operators::all() as $operator) {
            if(strpos($value, $operator) === 0) {
                $item->operator = $operator;
                $value = substr($value, strlen($item->operator));
                break;
            }
        }

        // Check for array-value
        if(substr($value, 0, 1) == '[' && substr($value, -1, 1) == ']') {
            $value = explode(',', substr($value, 1, -1));
        }

        // Strings
        if(is_array($value)) {
            foreach($value as &$val)
                $val = trim($val, "\"");
        } else
            $value = trim($value, "\"");

        $item->value = $value;

        return $item;
    }

    public function isRelationFilter(): bool {
        return strpos($this->property, '.') !== false;
    }

}