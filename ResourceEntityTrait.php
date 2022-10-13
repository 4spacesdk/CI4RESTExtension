<?php namespace RestExtension;

use DebugTool\Data;
use OrmExtension\DataMapper\ModelDefinitionCache;
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
        Data::debug(get_called_class(), 'post');
        $className = get_called_class();

        /** @var ResourceEntityInterface|Entity $item */
        $item = new $className();
        /** @var Model|ResourceBaseModelInterface|ResourceModelInterface $model */
        $model = $item->_getModel();

        // NB !! Is this okay? Client want to send relations as objects. But post should always create.
        if (isset($data['id']) && $data['id'] > 0) {
            /** @var Entity $entity */
            $entity = new $className();
            $entity->find($data[$model->getPrimaryKey()]); // Have to do a get, it might be saved later
            $entity->fill($data);
            return $entity;
        }

        $item->populatePatch($data);
        if (!$model->isRestCreationAllowed($item)) {
            Data::debug(get_class($item), "ERROR", ErrorCodes::InsufficientAccess);
            return $item;
        }
        $item->save();

        // Create relations
        if (is_array($data)) {
            $populateIgnore = $item->getPopulateIgnore();
            $relations = $model->getRelations();
            foreach ($relations as $relation) {
                switch ($relation->getType()) {
                    case RelationDef::HasOne:

                        $relationName = $relation->getSimpleName();
                        if (isset($data[$relationName]) && !in_array($relationName, $populateIgnore)) {
                            /** @var Entity|ResourceEntityInterface $entityName */
                            $entityName = $relation->getEntityName();
                            $relationEntity = $entityName::post($data[$relationName]);

                            $item->save($relationEntity, $relation->getName());
                            $item->relationAdded($relationEntity);

                            $item->{$relationName} = $relationEntity;
                        }

                        break;
                    case RelationDef::HasMany:

                        $relationName = plural($relation->getSimpleName());
                        if (isset($data[$relationName]) && !in_array($relationName, $populateIgnore)) {
                            /** @var ResourceEntityInterface|Entity $entityName */
                            $entityName = $relation->getEntityName();
                            foreach ($data[$relationName] as $dataItem) {
                                $relationEntity = $entityName::post($dataItem);
                                $item->save($relationEntity, $relation->getName());
                                $item->relationAdded($relationEntity);

                                if (!isset($item->{$relationName})) {
                                    $relationClass = $relation->getEntityName();
                                    $item->{$relationName} = new $relationClass();
                                }

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
        /** @var Model|ResourceBaseModelInterface|ResourceModelInterface $model */
        $model = $item->_getModel();

        $item = $model
            ->where($model->getPrimaryKey(), $id)
            ->find();
        $item->populatePut($data);
        if (!$model->isRestUpdateAllowed($item)) {
            Data::debug(get_class($item), "ERROR", ErrorCodes::InsufficientAccess);
            return $item;
        }
        $item->save();

        // Update relations
        if (is_array($data)) {
            $populateIgnore = $item->getPopulateIgnore();
            $relations = $model->getRelations();
            foreach ($relations as $relation) {
                switch ($relation->getType()) {
                    case RelationDef::HasOne:

                        $relationName = $relation->getSimpleName();
                        if (isset($data[$relationName]) && !in_array($relationName, $populateIgnore)) {

                            /** @var ResourceEntityInterface|Entity $entityName */
                            $entityName = $relation->getEntityName();

                            $dataItem = $data[$relationName];
                            if (isset($dataItem['id']) && $dataItem['id'] > 0)
                                $relationEntity = $entityName::put($dataItem['id'], $dataItem);
                            else
                                $relationEntity = $entityName::post($dataItem);

                            $item->save($relationEntity, $relation->getName());
                            $item->relationAdded($relationEntity);

                            $item->{$relationName} = $relationEntity;
                        }

                        break;
                    case RelationDef::HasMany:

                        $relationName = plural($relation->getSimpleName());
                        if (isset($data[$relationName]) && !in_array($relationName, $populateIgnore)) {
                            /** @var ResourceEntityInterface|Entity $entityName */
                            $entityName = $relation->getEntityName();

                            /** @var ResourceEntityInterface|Entity $oldRelations */
                            $oldRelations = $item->{$relationName};
                            $oldRelations->find();
                            $oldIds = [];
                            foreach ($oldRelations as $oldRelation)
                                $oldIds[$oldRelation->id] = $oldRelation;

                            $relationClass = $relation->getEntityName();
                            $item->{$relationName} = new $relationClass();

                            $newRelations = [];
                            foreach ($data[$relationName] as $dataItem) {
                                if (isset($dataItem['id']) && $dataItem['id'] > 0)
                                    $relationEntity = $entityName::put($dataItem['id'], $dataItem);
                                else
                                    $relationEntity = $entityName::post($dataItem);

                                if (!isset($oldIds[$relationEntity->id])) {
                                    $oldRelations->add($relationEntity);
                                    $newRelations[] = $relationEntity;
                                } else
                                    unset($oldIds[$relationEntity->id]);

                                $item->{$relationName}->add($relationEntity);
                            }

                            foreach ($oldIds as $id => $oldRelation) {
                                $oldRelations->remove($oldRelation);
                                $item->delete($oldRelation);
                                $item->relationRemoved($oldRelation);
                            }
                            foreach ($newRelations as $newRelation) {
                                $item->save($newRelation, $relation->getName());
                                $item->relationAdded($newRelation);
                            }
                        }

                        break;
                }
            }
        }

        /** @var Model|ResourceBaseModelInterface|ResourceModelInterface $model */
        //$model = $item->getModel();
        //$model->applyRestGetOneRelations($item);

        return $item;
    }

    public static function patch($id, $data) {
        Data::debug(get_called_class(), 'patch', $id);
        $className = get_called_class();
        /** @var ResourceEntityInterface|Entity $item */
        $item = new $className();
        /** @var Model|ResourceBaseModelInterface|ResourceModelInterface $model */
        $model = $item->_getModel();

        $item = $model
            ->where($model->getPrimaryKey(), $id)
            ->find();
        if ($item->populatePatch($data)) {
            if (!$model->isRestUpdateAllowed($item)) {
                Data::debug(get_class($item), "ERROR", ErrorCodes::InsufficientAccess, 'Update not allowed');
                return $item;
            }
            $item->save();
        } else {
            Data::debug(get_class($item), "Nothing to patch, skipping save");
        }

        // Update relations
        if (is_array($data)) {
            $populateIgnore = $item->getPopulateIgnore();
            $relations = $model->getRelations();
            foreach ($relations as $relation) {
                switch ($relation->getType()) {
                    case RelationDef::HasOne:

                        $relationName = $relation->getSimpleName();
                        if (isset($data[$relationName]) && !in_array($relationName, $populateIgnore)) {

                            /** @var ResourceEntityInterface|Entity $entityName */
                            $entityName = $relation->getEntityName();

                            $dataItem = $data[$relationName];
                            if (isset($dataItem['id']) && $dataItem['id'] > 0)
                                $relationEntity = $entityName::patch($dataItem['id'], $dataItem);
                            else
                                $relationEntity = $entityName::post($dataItem);

                            $item->save($relationEntity, $relation->getName());
                            $item->relationAdded($relationEntity);

                            $item->{$relationName} = $relationEntity;
                        }

                        break;
                    case RelationDef::HasMany:

                        $relationName = plural($relation->getSimpleName());
                        if (isset($data[$relationName]) && !in_array($relationName, $populateIgnore)) {
                            /** @var ResourceEntityInterface|Entity $entityName */
                            $entityName = $relation->getEntityName();

                            /** @var ResourceEntityInterface|Entity $oldRelations */
                            $oldRelations = $item->{$relationName};
                            $oldRelations->find();
                            $oldIds = [];
                            foreach ($oldRelations as $oldRelation) {
                                if (!isset($oldIds[$oldRelation->id])) {
                                    $oldIds[$oldRelation->id] = [];
                                }
                                $oldIds[$oldRelation->id][] = $oldRelation;
                            }

                            $relationClass = $relation->getEntityName();
                            $item->{$relationName} = new $relationClass();

                            $newRelations = [];
                            foreach ($data[$relationName] as $dataItem) {
                                if (isset($dataItem['id']) && $dataItem['id'] > 0)
                                    $relationEntity = $entityName::patch($dataItem['id'], $dataItem);
                                else
                                    $relationEntity = $entityName::post($dataItem);

                                if (!isset($oldIds[$relationEntity->id])) {
                                    $oldRelations->add($relationEntity);
                                    $newRelations[] = $relationEntity;
                                } else {
                                    array_shift($oldIds[$relationEntity->id]);
                                    if (count($oldIds[$relationEntity->id]) == 0) {
                                        unset($oldIds[$relationEntity->id]);
                                    }
                                }

                                $item->{$relationName}->add($relationEntity);
                            }

                            foreach ($oldIds as $id => $oldRelationsToBeRemoved) {
                                foreach ($oldRelationsToBeRemoved as $oldRelation) {
                                    $oldRelations->remove($oldRelation);
                                    $item->delete($oldRelation);
                                    $item->relationRemoved($oldRelation);
                                }
                            }
                            foreach ($newRelations as $newRelation) {
                                $item->save($newRelation, $relation->getName());
                                $item->relationAdded($newRelation);
                            }
                        }

                        break;
                }
            }
        }

        /** @var Model|ResourceBaseModelInterface|ResourceModelInterface $model */
        //$model = $item->getModel();
        //$model->applyRestGetOneRelations($item);

        return $item;
    }

    /**
     * @param array $data
     */
    public function populatePut($data) {
        if ($data) {
            /** @var Model|QueryBuilderInterface $model */
            $model = $this->_getModel();

            $fieldData = ModelDefinitionCache::getFieldData($model->getEntityName());
            $fieldName2Type = [];
            foreach ($fieldData as $field) $fieldName2Type[$field->name] = $field->type;

            $fields = $model->getTableFields();
            $fields = array_diff($fields, $this->getPopulateIgnore());
            $fields = array_diff($fields, $this->hiddenFields);
            foreach ($fields as $field) {
                if (isset($data[$field])) {
                    switch ($fieldName2Type[$field]) {
                        case 'datetime':
                            $this->{$field} = date('Y-m-d H:i:s', strtotime($data[$field]));
                            break;
                        default:
                            $this->{$field} = $data[$field];
                    }
                } else
                    $this->{$field} = null;
            }
        }
    }

    /**
     * @param array $data
     * @return bool
     */
    public function populatePatch($data) {
        if ($data) {
            /** @var Model|QueryBuilderInterface $model */
            $model = $this->_getModel();

            $fieldData = ModelDefinitionCache::getFieldData($model->getEntityName());
            $fieldName2Type = [];
            foreach ($fieldData as $field) {
                $fieldName2Type[$field->name] = $field;
            }

            $fields = array_keys($data);
            $fields = array_intersect($fields, $model->getTableFields());
            $fields = array_diff($fields, $this->getPopulateIgnore());
            foreach ($data as $field => $value) {
                if (in_array($field, $fields)) {
                    $fieldData = $fieldName2Type[$field];
                    if ($fieldData->nullable && is_null($value)) {
                        $this->{$field} = null;
                    } else {
                        switch ($fieldName2Type[$field]->type) {
                            case 'int':
                                $this->{$field} = (int)$value;
                                break;
                            case 'float':
                            case 'double':
                            case 'decimal':
                                $this->{$field} = (double)$value;
                                break;
                            case 'tinyint':
                                $this->{$field} = (bool)$value;
                                break;
                            case 'datetime':
                                $this->{$field} = $value ? date('Y-m-d H:i:s', strtotime($value)) : null;
                                break;
                            default:
                                $this->{$field} = $value;
                        }
                    }
                }
            }
        }
        return $this->hasChanged();
    }

}
