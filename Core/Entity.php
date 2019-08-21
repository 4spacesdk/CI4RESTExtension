<?php namespace RestExtension\Core;

use OrmExtension\Extensions\Model;
use RestExtension\Entities\User;
use RestExtension\ResourceEntityInterface;
use RestExtension\ResourceEntityTrait;

/**
 * Created by PhpStorm.
 * User: martin
 * Date: 12/11/2018
 * Time: 17.40
 *
 * @property string $created
 * @property string $updated
 * @property int $created_by_id
 * @property User $created_by
 * @property int $updated_by_id
 * @property User $updated_by
 * @property int $deletion_id
 */
class Entity extends \OrmExtension\Extensions\Entity implements ResourceEntityInterface {

    use ResourceEntityTrait;

    /**
     * @return Model|\OrmExtension\DataMapper\QueryBuilderInterface|Model
     */
    public function _getModel() {
        return parent::_getModel();
    }

    /**
     * @return string[]
     */
    public function getPopulateIgnore() {
        return [
            'id', 'created', 'updated', 'deletion_id', 'created_by_id', 'updated_by_id'
        ];
    }

    /**
     * @param Entity|ResourceEntityInterface
     */
    public function relationAdded($relation) {

    }

    /**
     * @param ResourceEntityInterface|Entity $relation
     */
    public function relationRemoved($relation) {

    }
}
