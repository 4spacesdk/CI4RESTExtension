<?php namespace RestExtension\Filter;
/**
 * Created by PhpStorm.
 * User: martin
 * Date: 2018-12-04
 * Time: 13:08
 */
class Operators {

    const Equal                 = '=';
    const Not                   = '-';
    const GreaterThan           = '>';
    const GreaterThanOrEqual    = '>=';
    const LessThan              = '<';
    const LessThanOrEqual       = '<=';

    public static function all() {
        return [
            self::GreaterThanOrEqual,
            self::LessThanOrEqual,
            self::LessThan,
            self::Equal,
            self::Not,
            self::GreaterThan
        ];
    }

}