<?php namespace RestExtension;
use DebugTool\Data;
use OrmExtension\DataMapper\RelationDef;
use OrmExtension\Extensions\Entity;
use OrmExtension\Extensions\Model;
use RestExtension\Filter\Operators;
use RestExtension\Filter\QueryFilter;
use RestExtension\Filter\QueryParser;

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
        Data::debug(get_class($this), "restGet");

        if($this instanceof Model) {
            if($this instanceof ResourceModelInterface) {

                if($id) $this->where('id', $id);

                foreach($queryParser->getFilters() as $filter) $this->apply($filter);

                $this->preRestGet($queryParser, $id);

                if($queryParser->isCount()) {
                    return $this->countAllResults();
                }

                if($queryParser->hasLimit()) $this->limit($queryParser->getLimit());
                if($queryParser->hasOffset()) $this->offset($queryParser->getOffset());

                /** @var Entity $items */
                $items = $this->find();

                if($items->exists()) {
                    if($id) {
                        $item = $items->first();
                        $this->applyRestGetOneRelations($item);
                    } else {
                        $this->appleRestGetManyRelations($items);

                    }
                }

                $this->postRestGet($queryParser, $items);

                return $items;

            }

            return $this->find();
        } else
            return new Entity();
    }

    /**
     * @param QueryFilter $filter
     */
    public function apply(QueryFilter $filter) {
        Data::debug(get_class($this), "apply", $filter->property, $filter->operator, is_array($filter->value) ? 'array' : $filter->value);

        if($filter->isRelationFilter()) {

            /** @var RelationDef[] $name2relation */
            $name2relation = [];
            /** @var RelationDef[] $relations */
            $relations = $this->getRelations();
            foreach($relations as $relation)
                $name2relation[$relation->getSimpleName()] = $relation;

            $classes = explode('.', $filter->property);
            $field = array_pop($classes);

            $relations = [];
            foreach($classes as $class) {
                if(isset($name2relation[$class]))
                    $relations[] = $name2relation[$class]->getName();
                else
                    Data::debug(get_class($this), "ERROR", "Unknown relation", $class);
            }

            switch($filter->operator) {
                case Operators::Search:

                    if(is_array($filter->value)) {
                        $this->groupStart();
                        foreach($filter->value as $value)
                            $this->orLikeRelated($relations, $field, $value, 'both', null, true);
                        $this->groupEnd();
                    } else
                        $this->likeRelated($relations, $field, $filter->value, 'both', null, true);
                    break;

                    break;
                case Operators::Not:
                    if(is_array($filter->value))
                        $this->whereNotInRelated($relations, $field, $filter->value);
                    else
                        $this->whereRelated($relations, "{$field} !=", $filter->value);
                    break;
                default:
                    if(is_array($filter->value))
                        $this->whereInRelated($relations, "{$field} {$filter->operator}", $filter->value);
                    else
                        $this->whereRelated($relations, "{$field} {$filter->operator}", $filter->value);
                    break;
            }

        } else {

            switch($filter->operator) {

                case Operators::Search:
                    if(is_array($filter->value)) {
                        $this->groupStart();
                        foreach($filter->value as $value)
                            $this->orLike($filter->property, $value, 'both', null, true);
                        $this->groupEnd();
                    } else
                        $this->like($filter->property, $filter->value, 'both', null, true);
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
        /** @var RelationDef $relation */
        foreach($this->getRelations() as $relation) {
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
                    }
                    break;
            }
        }
    }

}