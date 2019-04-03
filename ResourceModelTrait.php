<?php namespace RestExtension;
use DebugTool\Data;
use OrmExtension\DataMapper\RelationDef;
use OrmExtension\Extensions\Entity;
use OrmExtension\Extensions\Model;
use RestExtension\Filter\Operators;
use RestExtension\Filter\QueryFilter;
use RestExtension\Includes\QueryInclude;
use RestExtension\Ordering\QueryOrder;

/**
 * Created by PhpStorm.
 * User: martin
 * Date: 2018-12-04
 * Time: 14:30
 */
trait ResourceModelTrait {

    /**
     * @param int $id
     * @param QueryParser $queryParser
     * @return Entity|array|object
     */
    public function restGet($id, $queryParser) {
        Data::debug(get_class($this), "restGet", $id);

        if($this instanceof Model) {
            if($this instanceof ResourceModelInterface) {

                if($id) $this->where('id', $id);

                $this->preRestGet($queryParser, $id);

                foreach($queryParser->getIncludes() as $include) $this->applyIncludeOne($include);
                foreach($queryParser->getFilters() as $filter) $this->applyFilter($filter);
                $searchFilters = $queryParser->getSearchFilters();
                if(count($searchFilters)) {
                    $this->groupStart();
                    foreach($searchFilters as $filter) $this->applyFilter($filter);
                    $this->groupEnd();
                }

                if($queryParser->isCount()) {
                    $count = $this->distinct('id')->countAllResults();
                    //Data::lastQuery();
                    return $count;
                }

                if($queryParser->hasLimit()) $this->limit($queryParser->getLimit());
                if($queryParser->hasOffset()) $this->offset($queryParser->getOffset());
                foreach($queryParser->getOrdering() as $order) $this->applyOrder($order);

                /** @var Entity $items */
                $items = $this
                    ->groupBy('id')
                    ->find();

                //Data::lastQuery();

                if($items->exists()) {
                    if($id) {
                        $item = $items->first();
                        foreach($queryParser->getIncludes() as $include) $this->applyIncludeMany($items, $include);
                        $this->applyRestGetOneRelations($item);
                    } else {
                        foreach($queryParser->getIncludes() as $include) $this->applyIncludeMany($items, $include);
                        $this->appleRestGetManyRelations($items);
                    }

                    $this->postRestGet($queryParser, $items);
                }

                return $items;

            } else
                return $this->find();
        } else
            return new Entity();
    }

    public function applyOrder(QueryOrder $order) {
        Data::debug(get_class($this), "apply order", $order->property, $order->direction);

        $classes = explode('.', $order->property);
        $field = array_pop($classes);

        /** @var RelationDef[] $relations */
        $relations = $this->getRelation($classes, true);
        $relationNames = [];
        foreach($relations as $relation) {
            $relationNames[] = $relation->getName();
        }
        if(count($relationNames) > 0)
            $this->orderByRelated($relationNames, $field, $order->direction);
        else
            $this->orderBy($field, $order->direction);
    }


    /**
     * @param QueryInclude $include
     */
    public function applyIncludeOne(QueryInclude $include) {
        /** @var RelationDef[] $relations */
        $relations = $this->getRelation(explode('.', $include->property), true);
        $relationNames = [];
        foreach($relations as $relation) {
            if($relation->getType() != RelationDef::HasOne) return;
            $relationNames[] = $relation->getName();
        }
        if(count($relationNames)) $this->includeRelated($relationNames);
    }

    /**
     * @param Entity $items
     * @param QueryInclude $include
     */
    public function applyIncludeMany(Entity $items, QueryInclude $include) {
        /** @var RelationDef[] $relations */
        $relations = $this->getRelation(explode('.', $include->property), true);
        foreach($relations as $relation) {
            if($relation->getType() == RelationDef::HasOne) continue;
            $propertyName = plural($relation->getSimpleName());

            $modelName = $relation->getClass();
            foreach($items as $item) {
                $model = new $modelName();

                if($model instanceof ResourceBaseModelInterface) {
                    $queryParser = clone $include->queryParser;
                    $queryParser->parseFilter("{$relation->getSimpleOtherField()}.id:{$item->id}");
                    $items = $model->restGet(null, $queryParser);
                    $item->{$propertyName} = $items;
                }

            }
        }

    }

    /**
     * @param QueryFilter $filter
     */
    public function applyFilter(QueryFilter $filter) {
        Data::debug(get_class($this), "apply", $filter->property, $filter->operator, is_array($filter->value) ? 'array' : $filter->value);

        if($filter->isRelationFilter()) {

            $classes = explode('.', $filter->property);
            $field = array_pop($classes);
            /** @var RelationDef $relation */
            $relations = [];
            foreach($this->getRelation($classes, true) as $relation) {
                $relations[] = $relation->getName();
            }

            switch($filter->operator) {
                case Operators::Search:

                    if(is_array($filter->value)) {
                        $this->orGroupStart();
                        foreach($filter->value as $value)
                            $this->orLikeRelated($relations, $field, $value, 'both', null, true);
                        $this->groupEnd();
                    } else
                        $this->orLikeRelated($relations, $field, $filter->value, 'both', null, true);
                    break;

                case Operators::Not:
                    if(is_array($filter->value))
                        $this->whereNotInRelated($relations, $field, $filter->value);
                    else
                        $this->whereRelated($relations, "{$field} !=", $filter->value);
                    break;
                default:
                    if(is_array($filter->value))
                        $this->whereInRelated($relations, $field, $filter->value);
                    else
                        $this->whereRelated($relations, "{$field} {$filter->operator}", $filter->value);
                    break;
            }

        } else {

            switch($filter->operator) {

                case Operators::Search:
                    if(is_array($filter->value)) {
                        $this->orGroupStart();
                        foreach($filter->value as $value)
                            $this->orLike($filter->property, $value, 'both', null, true);
                        $this->groupEnd();
                    } else
                        $this->orLike($filter->property, $filter->value, 'both', null, true);
                    break;

                case Operators::Not:
                    if(is_array($filter->value))
                        $this->whereNotIn($filter->property, $filter->value);
                    else
                        $this->where("$filter->property !=", $filter->value);
                    break;

                default:
                    if(is_array($filter->value))
                        $this->whereIn($filter->property, $filter->value);
                    else
                        $this->where("$filter->property $filter->operator", $filter->value);
                    break;
            }

        }

    }

    /**
     * @param Entity $item
     */
    public function applyRestGetOneRelations($item) {
        $ignored = $this->ignoredRestGetOnRelations();
        /** @var RelationDef $relation */
        foreach($this->getRelations() as $relation) {
            if(in_array($relation->getName(), $ignored)) continue;
            //Data::debug(get_class($this), "running", $relation->getName(), plural($relation->getSimpleName()));
            switch($relation->getType()) {
                case RelationDef::HasOne:
                    $relationName = $relation->getSimpleName();
                    if(!isset($item->{$relationName})) {
                        $rel = $item->{$relationName};
                        if($rel instanceof Entity)
                            $rel->find();
                        else
                            Data::debug(get_class($this), "ERROR", $relationName, 'not found for', get_class($item));
                    }
                    break;
                case RelationDef::HasMany:
                    $relationName = plural($relation->getSimpleName());
                    if(!isset($item->{$relationName})) {
                        $rel = $item->{$relationName};
                        if($rel instanceof Entity)
                            $rel->find();
                        else
                            Data::debug(get_class($this), "ERROR", $relationName, 'not found for', get_class($item));
                    } else
                        Data::debug(get_class($this), "ERROR", $relationName, 'already set (ignored)');
                    break;
            }
        }
    }

    public function ignoredRestGetOnRelations() {
        return [];
    }

}