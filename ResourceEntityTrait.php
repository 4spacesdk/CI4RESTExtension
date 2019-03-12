<?php namespace RestExtension;
use App\Entities\ProjectStatus;
use App\Extensions\MyEntity;
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

        // OBS !! Is this okay? Client want to send relations as objects. But post should always create.
        if(isset($data['id']) && $data['id'] > 0) {
            /** @var MyEntity $entity */
            $entity = new $className();
            return $entity->find($data['id']); // Have to do a get, it might be saved later
        }

        /** @var ResourceEntityInterface|Entity $item */
        $item = new $className();
        /** @var Model|ResourceBaseModelInterface|ResourceModelInterface $model */
        $model = $item->getModel();

        $item->populatePatch($data);
        if(!$model->isRestCreationAllowed($item)) {
            Data::debug(get_class($item), "ERROR", ErrorCodes::InsufficientAccess);
            return $item;
        }
        $item->save();

        // Create relations
        if(is_array($data)) {
            $relations = $model->getRelations();
            foreach($relations as $relation) {
                switch($relation->getType()) {
                    case RelationDef::HasOne:

                        $relationName = $relation->getSimpleName();
                        if(isset($data[$relationName])) {
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
                        if(isset($data[$relationName])) {
                            /** @var ResourceEntityInterface|Entity $entityName */
                            $entityName = $relation->getEntityName();
                            foreach($data[$relationName] as $dataItem) {
                                $relationEntity = $entityName::post($dataItem);
                                $item->save($relationEntity, $relation->getName());
                                $item->relationAdded($relationEntity);

                                if(!isset($item->{$relationName}))
                                    $item->{$relationName} = $relationEntity;
                                else
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
        $model = $item->getModel();

        $item = $model
            ->where('id', $id)
            ->find();
        $item->populatePut($data);
        if(!$model->isRestUpdateAllowed($item)) {
            Data::debug(get_class($item), "ERROR", ErrorCodes::InsufficientAccess);
            return $item;
        }
        $item->save();

        // Update relations
        if(is_array($data)) {
            $relations = $model->getRelations();
            foreach($relations as $relation) {
                switch($relation->getType()) {
                    case RelationDef::HasOne:

                        $relationName = $relation->getSimpleName();
                        if(isset($data[$relationName])) {

                            /** @var ResourceEntityInterface|Entity $entityName */
                            $entityName = $relation->getEntityName();

                            $dataItem = $data[$relationName];
                            if(isset($dataItem['id']) && $dataItem['id'] > 0)
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
                        if(isset($data[$relationName])) {
                            /** @var ResourceEntityInterface|Entity $entityName */
                            $entityName = $relation->getEntityName();

                            /** @var ResourceEntityInterface|Entity $oldRelations */
                            $oldRelations = $item->{$relationName};
                            $oldRelations->find();
                            $oldIds = [];
                            foreach($oldRelations as $oldRelation)
                                $oldIds[$oldRelation->id] = $oldRelation;

                            $relationClass = $relation->getEntityName();
                            $item->{$relationName} = new $relationClass();

                            $newRelations = [];
                            foreach($data[$relationName] as $dataItem) {
                                if(isset($dataItem['id']) && $dataItem['id'] > 0)
                                    $relationEntity = $entityName::put($dataItem['id'], $dataItem);
                                else
                                    $relationEntity = $entityName::post($dataItem);

                                if(!isset($oldIds[$relationEntity->id])) {
                                    $oldRelations->add($relationEntity);
                                    $newRelations[] = $relationEntity;
                                } else
                                    unset($oldIds[$relationEntity->id]);

                                $item->{$relationName}->add($relationEntity);
                            }

                            foreach($oldIds as $id => $oldRelation) {
                                $oldRelations->remove($oldRelation);
                                $item->delete($oldRelation);
                                $item->relationRemoved($oldRelation);
                            }
                            foreach($newRelations as $newRelation) {
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
        $model = $item->getModel();

        $item = $model
            ->where('id', $id)
            ->find();
        if($item->populatePatch($data)) {
            if(!$model->isRestUpdateAllowed($item)) {
                Data::debug(get_class($item), "ERROR", ErrorCodes::InsufficientAccess, 'Update not allowed');
                return $item;
            }
            $item->save();
        } else
            Data::debug(get_class($item), "Nothing to patch, skipping save");

        // Update relations
        if(is_array($data)) {
            $relations = $model->getRelations();
            foreach($relations as $relation) {
                switch($relation->getType()) {
                    case RelationDef::HasOne:

                        $relationName = $relation->getSimpleName();
                        if(isset($data[$relationName])) {

                            /** @var ResourceEntityInterface|Entity $entityName */
                            $entityName = $relation->getEntityName();

                            $dataItem = $data[$relationName];
                            if(isset($dataItem['id']) && $dataItem['id'] > 0)
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
                        if(isset($data[$relationName])) {
                            /** @var ResourceEntityInterface|Entity $entityName */
                            $entityName = $relation->getEntityName();

                            /** @var ResourceEntityInterface|Entity $oldRelations */
                            $oldRelations = $item->{$relationName};
                            $oldRelations->find();
                            $oldIds = [];
                            foreach($oldRelations as $oldRelation)
                                $oldIds[$oldRelation->id] = $oldRelation;

                            $relationClass = $relation->getEntityName();
                            $item->{$relationName} = new $relationClass();

                            $newRelations = [];
                            foreach($data[$relationName] as $dataItem) {
                                if(isset($dataItem['id']) && $dataItem['id'] > 0)
                                    $relationEntity = $entityName::patch($dataItem['id'], $dataItem);
                                else
                                    $relationEntity = $entityName::post($dataItem);

                                if(!isset($oldIds[$relationEntity->id])) {
                                    $oldRelations->add($relationEntity);
                                    $newRelations[] = $relationEntity;
                                } else
                                    unset($oldIds[$relationEntity->id]);

                                $item->{$relationName}->add($relationEntity);
                            }

                            foreach($oldIds as $id => $oldRelation) {
                                $oldRelations->remove($oldRelation);
                                $item->delete($oldRelation);
                                $item->relationRemoved($oldRelation);
                            }
                            foreach($newRelations as $newRelation) {
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
        if($data) {
            /** @var Model|QueryBuilderInterface $model */
            $model = $this->getModel();

            $fieldData = ModelDefinitionCache::getFieldData($model->getEntityName());
            $fieldName2Type = [];
            foreach($fieldData as $field) $fieldName2Type[$field->name] = $field->type;

            $fields = $model->getTableFields();
            $fields = array_diff($fields, $this->getPopulateIgnore());
            $fields = array_diff($fields, $this->hiddenFields);
            foreach($fields as $field) {
                if(isset($data[$field])) {
                    switch($fieldName2Type[$field]) {
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
        $hasChange = false;
        if($data) {
            /** @var Model|QueryBuilderInterface $model */
            $model = $this->getModel();

            $fieldData = ModelDefinitionCache::getFieldData($model->getEntityName());
            $fieldName2Type = [];
            foreach($fieldData as $field) $fieldName2Type[$field->name] = $field->type;

            $fields = array_keys($data);
            $fields = array_intersect($fields, $model->getTableFields());
            $fields = array_diff($fields, $this->getPopulateIgnore());
            foreach($data as $field => $value) {
                if(in_array($field, $fields)) {
                    switch($fieldName2Type[$field]) {
                        case 'datetime':
                            $this->{$field} = $value ? date('Y-m-d H:i:s', strtotime($value)) : null;
                            break;
                        default:
                            $this->{$field} = $value;
                    }
                    $hasChange = true;
                }
            }
        }
        return $hasChange;
    }

}