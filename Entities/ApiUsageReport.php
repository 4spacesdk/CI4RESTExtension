<?php namespace RestExtension\Entities;

use OrmExtension\Extensions\Entity;

/**
 * Class ApiUsageReport
 * @package RestExtension\Entities
 * @property int $id
 * @property string $client_id
 * @property string $date
 * @property int $usage
 */
class ApiUsageReport extends Entity {

    /**
     * @return \ArrayIterator|\OrmExtension\Extensions\Entity[]|\Traversable|ApiUsageReport[]
     */
    public function getIterator() {
        return parent::getIterator();
    }

}
