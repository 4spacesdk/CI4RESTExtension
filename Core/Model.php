<?php namespace RestExtension\Core;

use CodeIgniter\Database\ConnectionInterface;
use CodeIgniter\Validation\ValidationInterface;
use OrmExtension\Extensions\Entity;
use RestExtension\ResourceBaseModelInterface;
use RestExtension\ResourceModelTrait;
use RestExtension\RestRequest;

/**
 * Class MyModel
 * @package RestExtension\Core
 */
class Model extends \OrmExtension\Extensions\Model implements ResourceBaseModelInterface {

    use ResourceModelTrait;

    public function __construct(ConnectionInterface $db = null, ValidationInterface $validation = null) {
        parent::__construct($db, $validation);
        if(in_array('created', $this->getTableFields()))
            $this->createdField = 'created';
        if(in_array('updated', $this->getTableFields()))
            $this->updatedField = 'updated';

        if(in_array($this->createdField, $this->getTableFields()) && in_array($this->updatedField, $this->getTableFields()))
            $this->useTimestamps = true;
    }


    // <editor-fold name="Extending OrmExtension">

    /**
     * @param Entity $entity
     * @return bool
     * @throws \ReflectionException
     */
    public function save($entity): bool {
        if(isset(RestRequest::getInstance()->userId)) {
            if(!$entity->exists() && in_array('created_by_id', $this->getTableFields()))
                $entity->created_by_id = RestRequest::getInstance()->userId;
            if($entity->exists() && in_array('updated_by_id', $this->getTableFields()))
                $entity->updated_by_id = RestRequest::getInstance()->userId;
        }
        return parent::save($entity);
    }

    // </editor-fold>
}
