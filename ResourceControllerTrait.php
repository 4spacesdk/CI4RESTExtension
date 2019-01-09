<?php namespace RestExtension;

use CodeIgniter\HTTP\Request;
use OrmExtension\Extensions\Entity;
use OrmExtension\Extensions\Model;

/**
 * Created by PhpStorm.
 * User: martin
 * Date: 23/11/2018
 * Time: 15.28
 *
 * @property string $resource
 * @property Request $request
 */
trait ResourceControllerTrait {

    protected $resource = null;
    public $queryParser;

    public function _getResourceName() : string {
        if($this->resource)
            return $this->resource;
        else
            return str_replace('Controllers', 'Models', singular(get_class($this)).'Model');
    }

    public function get($id = 0) {
        /** @var Model|ResourceBaseModelInterface|ResourceModelInterface $model */
        $className = $this->_getResourceName();
        $model = new $className();
        $items = $model->restGet($id, $this->queryParser);
        if($id)
            $this->_setResource($items->first());
        else {
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

        if(is_array($this->request->getJSON())) {

            /** @var Entity $resources */
            $resources = new $entityName();
            foreach($data as $dataItem) {
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

        if($id) {

            $item = $entityName::put($id, $data);
            $this->_setResource($item);

        } else if(is_array($data)) {

            /** @var Entity $resources */
            $resources = new $entityName();
            foreach($data as $dataItem) {
                if(isset($dataItem['id']))
                    $resources->add($entityName::put($dataItem['id'], $dataItem));
            }
            $this->_setResources($resources);

        }

        $this->success();
    }

    public function patch($id = 0) {
        $className = $this->_getResourceName();

        /** @var Model $model */
        $model = new $className();
        /** @var Entity|ResourceEntityInterface $entityName */
        $entityName = $model->returnType;

        $data = $this->request->getJSON(true);

        if($id) {

            $item = $entityName::patch($id, $data);
            $this->_setResource($item);

        } else if(is_array($data)) {

            /** @var Entity $resources */
            $resources = new $entityName();
            foreach($data as $dataItem) {
                if(isset($dataItem['id']) && $dataItem['id'] > 0)
                    $resources->add($entityName::patch($dataItem['id'], $dataItem));
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
        $model->where('id', $id);

        /** @var Entity $item */
        $item = $model->find();

        if($item->exists()) {
            if(!$model->isRestDeleteAllowed($item))
                $this->error(ErrorCodes::InsufficientAccess, 403);
            $item->delete();
        }

        $this->_setResource($item);

        $this->success();
    }



}