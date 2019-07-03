<?php namespace RestExtension\Entities;

use OrmExtension\Extensions\Entity;

/**
 * Class OAuthScope
 * @package RestExtension
 * @property string $scope
 * @property bool $is_default
 * @property string $description
 */
class OAuthScope extends Entity {

    /**
     * @return \ArrayIterator|Entity[]|\Traversable|OAuthScope[]
     */
    public function getIterator() {return parent::getIterator();}
}
