<?php namespace RestExtension\Entities;

use OrmExtension\Extensions\Entity;

/**
 * Class Deletion
 * @package RestExtension\Entities
 */
class Deletion extends Entity {

    /**
     * @return \ArrayIterator|\OrmExtension\Extensions\Entity[]|\Traversable|Deletion[]
     */
    public function getIterator() {
        return parent::getIterator();
    }

}
