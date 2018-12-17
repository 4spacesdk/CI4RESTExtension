<?php namespace RestExtension\Includes;

use RestExtension\QueryParser;

/**
 * Created by PhpStorm.
 * User: martin
 * Date: 2018-12-04
 * Time: 13:05
 *
 * @property string $property
 * @property QueryParser $queryParser
 */
class QueryInclude {

    public static function parse($line) {
        $item = new QueryInclude();

        if(strpos($line, '?') !== false) {
            list($property, $parser) = explode('?', $line);
            $item->property = $property;
            $item->queryParser = QueryParser::parse($parser);
        } else {
            $item->property = $line;
        }
        return $item;
    }

}