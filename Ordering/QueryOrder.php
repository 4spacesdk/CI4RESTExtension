<?php namespace RestExtension\Ordering;

/**
 * Created by PhpStorm.
 * User: martin
 * Date: 2018-12-04
 * Time: 13:05
 *
 * @property string $property
 * @property string $direction
 */
class QueryOrder {

    public static function parse($line) {
        $parts = explode(':', $line);

        $item = new QueryOrder();
        $item->property = array_shift($parts);
        $item->direction = count($parts) ? array_shift($parts) : 'asc';

        return $item;
    }

}