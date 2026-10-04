<?php namespace RestExtension;

use CodeIgniter\HTTP\Request;
use OrmExtension\Extensions\Entity;
use OrmExtension\Extensions\Model;
use RestExtension\Exceptions\InsufficientAccessException;

/**
 * Created by PhpStorm.
 * User: martin
 * Date: 23/11/2018
 * Time: 15.28
 *
 * @property string $resource
 * @property Request $request
 * @property QueryParser $queryParser
 */
trait ResourceControllerTrait {

    protected $resource = null;
    public $queryParser;

    public function _getResourceName(): string {
        if ($this->resource) {
            return $this->resource;
        } else {
            return str_replace('Controllers', 'Models', singular(get_class($this)) . 'Model');
        }
    }

    public function get($id = 0) {
        /** @var Model|ResourceBaseModelInterface|ResourceModelInterface $model */
        $className = $this->_getResourceName();
        $model = new $className();
        $items = $model->restGet($id, $this->queryParser);
        if ($id) {
            // `first()` on an empty collection answers the entity itself, so an id that does
            // not exist used to be served as a complete resource with every column null. A
            // caller could not tell it apart from a row that is there, and anyone signed in
            // could read off the column names of every resource by asking for an id that is
            // not there.
            $item = $items->first();
            if (!$item->exists()) {
                $this->error(ErrorCodes::ResourceNotFound, 404);
                return;
            }
            $this->_setResource($item);
        } else {
            $this->_setResources($items);
        }
        $this->success();
    }

    public function post() {
        $className = $this->_getResourceName();

        /** @var Model $model */
        $model = new $className();
        /** @var Entity|ResourceEntityInterface $entityName */
        $entityName = $model->returnType;

        $data = $this->request->getJSON(true);

        if ($this->writesFollowRules($model)) {
            if ($this->isListOfRows($data)) {
                $this->error(ErrorCodes::OneResourcePerRequest, 400);
                return;
            }
            try {
                $item = $entityName::post($this->writableData($model, $data));
            } catch (InsufficientAccessException $e) {
                $this->error(ErrorCodes::InsufficientAccess, 403);
                return;
            }
            if (!$item->exists()) {
                $this->error(ErrorCodes::InsufficientAccess, 403);
                return;
            }
            $this->_setResource($item);
            $this->success();
            return;
        }

        if (is_array($this->request->getJSON())) {

            /** @var Entity $resources */
            $resources = new $entityName();
            foreach ($data as $dataItem) {
                $resources->add($entityName::post($dataItem));
            }
            $this->_setResources($resources);

        } else {

            $item = $entityName::post($data);
            $this->_setResource($item);

        }

        $this->success();
    }

    public function put($id = 0) {
        $className = $this->_getResourceName();

        /** @var Model $model */
        $model = new $className();
        /** @var Entity|ResourceEntityInterface $entityName */
        $entityName = $model->returnType;

        $data = $this->request->getJSON(true);

        if ($this->writesFollowRules($model)) {
            $this->updateFollowingRules($className, $id, $data, 'put');
            return;
        }

        if (is_array($this->request->getJSON())) {

            /** @var Entity $resources */
            $resources = new $entityName();
            foreach ($data as $dataItem) {
                $resources->add($entityName::put(isset($dataItem[$model->getPrimaryKey()]) ? $dataItem[$model->getPrimaryKey()] : 0, $dataItem));
            }
            $this->_setResources($resources);

        } else {

            $item = $entityName::put($id, $data);
            $this->_setResource($item);

        }

        $this->success();
    }

    public function patch($id = 0) {
        $className = $this->_getResourceName();

        /** @var Model $model */
        $model = new $className();
        $primaryKey = $model->getPrimaryKey();
        /** @var Entity|ResourceEntityInterface $entityName */
        $entityName = $model->returnType;

        $data = $this->request->getJSON(true);

        if ($this->writesFollowRules($model)) {
            $this->updateFollowingRules($className, $id, $data, 'patch');
            return;
        }

        if ($id) {

            $item = $entityName::patch($id, $data);
            $this->_setResource($item);

        } else if (is_array($data)) {

            /** @var Entity $resources */
            $resources = new $entityName();
            foreach ($data as $dataItem) {
                if (isset($dataItem[$primaryKey]) && $dataItem[$primaryKey] > 0)
                    $resources->add($entityName::patch($dataItem[$primaryKey], $dataItem));
                else
                    $resources->add($entityName::post($dataItem));
            }
            $this->_setResources($resources);

        }

        $this->success();
    }

    public function delete($id) {
        $className = $this->_getResourceName();

        /** @var Model|ResourceBaseModelInterface|ResourceModelInterface $model */
        $model = new $className();
        if ($this->writesFollowRules($model) && !(new $className())->isRestVisible($id, $this->queryParser)) {
            $this->error(ErrorCodes::ResourceNotFound, 404);
            return;
        }
        $model->where($model->getPrimaryKey(), $id);

        /** @var Entity $item */
        $item = $model->find();

        if ($item->exists()) {
            if (!$model->isRestDeleteAllowed($item)) {
                $this->error(ErrorCodes::InsufficientAccess, 403);
                return;
            }
            $item->delete();
        }

        $this->_setResource($item);

        $this->success();
    }



    // <editor-fold desc="Writes that follow the rules">

    /**
     * See ResourceModelTrait::writesFollowRules().
     *
     * @param Model $model
     */
    private function writesFollowRules($model): bool {
        return method_exists($model, 'writesFollowRules') && $model->writesFollowRules();
    }

    /**
     * PATCH or PUT of one row the caller may read, with what the body may write.
     *
     * @param string $className the model
     * @param mixed $id
     * @param mixed $data
     */
    private function updateFollowingRules(string $className, $id, $data, string $method): void {
        /** @var Model $model */
        $model = new $className();
        /** @var Entity|ResourceEntityInterface $entityName */
        $entityName = $model->returnType;
        if (!$id || $this->isListOfRows($data)) {
            $this->error(ErrorCodes::OneResourcePerRequest, 400);
            return;
        }
        if (!(new $className())->isRestVisible($id, $this->queryParser)) {
            $this->error(ErrorCodes::ResourceNotFound, 404);
            return;
        }
        try {
            $item = $entityName::$method($id, $this->writableData($model, $data));
        } catch (InsufficientAccessException $e) {
            $this->error(ErrorCodes::InsufficientAccess, 403);
            return;
        }
        $this->_setResource($item);
        $this->success();
    }

    /**
     * @param mixed $data
     */
    private function isListOfRows($data): bool {
        return is_array($data) && $data !== [] && array_keys($data) === range(0, count($data) - 1);
    }

    /**
     * The body without what a write may not carry: the id, which the URL gives or a POST makes,
     * and every relation given as an object - a relation is written by its column, which the
     * rules see.
     *
     * @param Model $model
     * @param mixed $data
     * @return mixed
     */
    private function writableData($model, $data) {
        if (!is_array($data)) {
            return $data;
        }
        unset($data[$model->getPrimaryKey()]);
        helper('inflector');
        foreach ($model->getRelations() as $relation) {
            unset($data[$relation->getSimpleName()], $data[plural($relation->getSimpleName())]);
        }
        return $data;
    }

    // </editor-fold>

}
