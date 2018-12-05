<?php namespace RestExtension;
use DebugTool\Data;
use OrmExtension\DataMapper\QueryBuilderInterface;
use OrmExtension\DataMapper\RelationDef;
use OrmExtension\Extensions\Entity;
use OrmExtension\Extensions\Model;

/**
 * Created by PhpStorm.
 * User: martin
 * Date: 2018-12-05
 * Time: 14:04
 */
trait ResourceEntityTrait {

    public static function post($data) {
        $className = get_called_class();
        /** @var ResourceEntityInterface|Entity $item */
        $item = new $className();
        $item->populatePatch($data);
        $item->save();

        // Create relations
        if(is_array($data)) {
            $relations = $item->getModel()->getRelations();
            foreach($relations as $relation) {
                switch($relation->getType()) {
                    case RelationDef::HasOne:

                        $relationName = $relation->getSimpleName();
                        if(isset($data[$relationName])) {
                            /** @var Entity $entityName */
                            $entityName = $relation->getEntityName();
                            $relationEntity = $entityName::post($data[$relationName]);
                            $item->save($relationEntity);
                            $item->relationAdded($relationEntity);

                            $item->{$relationName} = $relationEntity;
                        }

                        break;
                    case RelationDef::HasMany:

                        $relationName = plural($relation->getSimpleName());
                        if(isset($data[$relationName])) {
                            /** @var ResourceEntityInterface|Entity $entityName */
                            $entityName = $relation->getEntityName();
                            foreach($data[$relationName] as $dataItem) {
                                $relationEntity = $entityName::post($dataItem);
                                $item->save($relationEntity);
                                $item->relationAdded($relationEntity);

                                if(!isset($item->{$relationName})) $item->{$relationName} = $relationEntity;
                                $item->{$relationName}->add($relationEntity);
                            }
                        }

                        break;
                }
            }
        }

        return $item;
    }

    public static function put($id, $data) {
        Data::debug(get_called_class(), 'put', $id);
        $className = get_called_class();
        /** @var ResourceEntityInterface|Entity $item */
        $item = new $className();
        $item = $item->getModel()
            ->where('id', $id)
            ->find();
        $item->populatePut($data);
        $item->save();

        // Update relations
        if(is_array($data)) {
            $relations = $item->getModel()->getRelations();
            foreach($relations as $relation) {
                switch($relation->getType()) {
                    case RelationDef::HasOne:

                        $relationName = $relation->getSimpleName();
                        if(isset($data[$relationName])) {

                            /** @var ResourceEntityInterface|Entity $entityName */
                            $entityName = $relation->getEntityName();

                            $dataItem = $data[$relationName];
                            if(isset($dataItem['id']))
                                $relationEntity = $entityName::put($dataItem['id'], $dataItem);
                            else
                                $relationEntity = $entityName::post($dataItem);

                            $item->save($relationEntity);
                            $item->relationAdded($relationEntity);

                            $item->{$relationName} = $relationEntity;
                        }

                        break;
                    case RelationDef::HasMany:

                        $relationName = plural($relation->getSimpleName());
                        if(isset($data[$relationName])) {
                            /** @var ResourceEntityInterface|Entity $entityName */
                            $entityName = $relation->getEntityName();

                            /** @var ResourceEntityInterface|Entity $oldRelations */
                            $oldRelations = $item->{$relationName};
                            $oldRelations->find();
                            $oldIds = [];
                            foreach($oldRelations as $oldRelation)
                                $oldIds[$oldRelation->id] = $oldRelation;

                            $newRelations = [];
                            foreach($data[$relationName] as $dataItem) {
                                if(isset($dataItem['id']))
                                    $relationEntity = $entityName::put($dataItem['id'], $dataItem);
                                else
                                    $relationEntity = $entityName::post($dataItem);

                                if(!isset($oldIds[$relationEntity->id])) {
                                    $oldRelations->add($relationEntity);
                                    $newRelations[] = $relationEntity;
                                } else
                                    unset($oldIds[$relationEntity->id]);
                            }

                            foreach($oldIds as $id => $oldRelation) {
                                $oldRelations->remove($oldRelation);
                                $item->delete($oldRelation);
                            }
                            foreach($newRelations as $newRelation) {
                                $item->save($newRelation);
                                $item->relationAdded($newRelation);
                            }
                        }

                        break;
                }
            }
        }

        return $item;
    }

    public static function patch($id, $data) {
        Data::debug(get_called_class(), 'patch', $id);
        $className = get_called_class();
        /** @var ResourceEntityInterface|Entity $item */
        $item = new $className();
        $item = $item->getModel()
            ->where('id', $id)
            ->find();
        $item->populatePatch($data);
        $item->save();

        // Update relations
        if(is_array($data)) {
            $relations = $item->getModel()->getRelations();
            foreach($relations as $relation) {
                switch($relation->getType()) {
                    case RelationDef::HasOne:

                        $relationName = $relation->getSimpleName();
                        if(isset($data[$relationName])) {

                            /** @var ResourceEntityInterface|Entity $entityName */
                            $entityName = $relation->getEntityName();

                            $dataItem = $data[$relationName];
                            if(isset($dataItem['id']))
                                $relationEntity = $entityName::put($dataItem['id'], $dataItem);
                            else
                                $relationEntity = $entityName::post($dataItem);

                            $item->save($relationEntity);
                            $item->relationAdded($relationEntity);

                            $item->{$relationName} = $relationEntity;
                        }

                        break;
                    case RelationDef::HasMany:

                        $relationName = plural($relation->getSimpleName());
                        if(isset($data[$relationName])) {
                            /** @var ResourceEntityInterface|Entity $entityName */
                            $entityName = $relation->getEntityName();

                            /** @var ResourceEntityInterface|Entity $oldRelations */
                            $oldRelations = $item->{$relationName};
                            $oldRelations->find();
                            $oldIds = [];
                            foreach($oldRelations as $oldRelation)
                                $oldIds[$oldRelation->id] = $oldRelation;

                            $newRelations = [];
                            foreach($data[$relationName] as $dataItem) {
                                if(isset($dataItem['id']))
                                    $relationEntity = $entityName::patch($dataItem['id'], $dataItem);
                                else
                                    $relationEntity = $entityName::post($dataItem);

                                if(!isset($oldIds[$relationEntity->id])) {
                                    $oldRelations->add($relationEntity);
                                    $newRelations[] = $relationEntity;
                                } else
                                    unset($oldIds[$relationEntity->id]);
                            }

                            foreach($oldIds as $id => $oldRelation) {
                                $oldRelations->remove($oldRelation);
                                $item->delete($oldRelation);
                            }
                            foreach($newRelations as $newRelation) {
                                $item->save($newRelation);
                                $item->relationAdded($newRelation);
                            }
                        }

                        break;
                }
            }
        }

        /** @var Model|ResourceModelInterface $model */
        $model = $item->getModel();
        $model->applyRestGetOneRelations($item);

        return $item;
    }

    /**
     * @param $data
     * @return mixed
     */
    public function populatePut($data) {
        if($data) {
            /** @var Model|QueryBuilderInterface $model */
            $model = $this->getModel();
            $fields = $model->getTableFields();
            $fields = array_diff($fields, $this->getPopulateIgnore());
            foreach($fields as $field) {
                if(isset($data[$field])) {
                    $this->{$field} = $data[$field];
                } else
                    $this->{$field} = null;
            }
        }
        return $this;
    }

    /**
     * @param $data
     * @return mixed
     */
    public function populatePatch($data) {
        if($data) {
            /** @var Model|QueryBuilderInterface $model */
            $model = $this->getModel();
            $fields = array_keys($data);
            $fields = array_intersect($fields, $model->getTableFields());
            $fields = array_diff($fields, $this->getPopulateIgnore());
            foreach($data as $field => $value) {
                if(in_array($field, $fields)) {
                    $this->{$field} = $value;
                }
            }
        }
        return $this;
    }

}