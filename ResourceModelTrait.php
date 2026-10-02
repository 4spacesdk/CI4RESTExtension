<?php namespace RestExtension;

use DebugTool\Data;
use OrmExtension\DataMapper\RelationDef;
use OrmExtension\Extensions\Entity;
use OrmExtension\Extensions\Model;
use RestExtension\Fields\QueryField;
use RestExtension\Exceptions\InvalidRequestException;
use RestExtension\Filter\Operators;
use RestExtension\Filter\QueryFilter;
use RestExtension\Includes\QueryInclude;
use RestExtension\Ordering\QueryOrder;
use RestExtension\Relations\KeySet;
use OrmExtension\DataMapper\RelationLink;
use RestExtension\Relations\LinkedKeys;

/**
 * Created by PhpStorm.
 * User: martin
 * Date: 2018-12-04
 * Time: 14:30
 */
trait ResourceModelTrait {

    /**
     * @param int|string $primaryKey
     * @param QueryParser $queryParser
     * @return Entity|array|object
     */
    public function restGet($primaryKey, $queryParser) {
        Data::debug(get_class($this), "restGet", $primaryKey);

        if ($this instanceof Model) {
            if ($this instanceof ResourceModelInterface) {

                if ($primaryKey) {
                    $this->where($this->getPrimaryKey(), $primaryKey);
                }

                if ($queryParser->isCount()) {
                    $this->select($this->getPrimaryKey());
                }

                $this->preRestGet($queryParser, $primaryKey);

                $this->applyRestFilters($queryParser);

                if ($queryParser->isCount()) {
                    $count = $this->distinct(true)->countAllResults();
                    return $count;
                }

                foreach ($queryParser->getIncludes() as $include) {
                    if (!$include->ignoreAuto) {
                        $this->applyIncludeOne($include);
                    }
                }
                foreach ($queryParser->getFields() as $field) {
                    if (!$field->ignoreAuto) {
                        $this->applyField($field);
                    }
                }

                if ($queryParser->hasLimit()) {
                    $this->limit($queryParser->getLimit());
                }
                if ($queryParser->hasOffset()) {
                    $this->offset($queryParser->getOffset());
                }
                foreach ($queryParser->getOrdering() as $order) {
                    if (!$order->ignoreAuto) {
                        $this->applyOrder($order);
                    }
                }

                /** @var Entity $items */
                $items = $this
                    ->groupBy($this->getPrimaryKey())
                    ->find();

                //Data::lastQuery();

                if ($items->exists()) {
                    if ($primaryKey) {
                        $item = $items->first();
                        foreach ($queryParser->getIncludes() as $include) {
                            if (!$include->ignoreAuto) $this->applyIncludeMany($items, $include);
                        }
                        $this->applyRestGetOneRelations($item);
                    } else {
                        foreach ($queryParser->getIncludes() as $include) {
                            if (!$include->ignoreAuto) $this->applyIncludeMany($items, $include);
                        }
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

    /**
     * The query string's filters, the searches among them in a group of their own, ORed.
     *
     * @param QueryParser $queryParser
     */
    public function applyRestFilters($queryParser) {
        foreach ($queryParser->getFilters() as $filter) {
            if (!$filter->ignoreAuto) {
                $this->applyFilter($filter);
            }
        }
        $searchFilters = $queryParser->getSearchFilters();
        if (count($searchFilters)) {
            $this->groupStart();
            foreach ($searchFilters as $filter) {
                if (!$filter->ignoreAuto) {
                    $this->applyFilter($filter);
                }
            }
            $this->groupEnd();
        }
    }

    /**
     * `ordering=field:direction`, checked before it reaches the query builder.
     *
     * Neither half used to be looked at. A field that is not a column reached `orderBy()`
     * as it was written and came back as a `DatabaseException` - a 500 carrying the
     * statement it failed on - and a relation that does not exist came back as the ORM's
     * own `Failed to find relation`, which is a 500 as well. A direction that is not
     * `asc` or `desc` was worse for being quiet: CodeIgniter drops a direction it does not
     * recognise, so `name:sideways` answered `200 OK` sorted ascending, and a client that
     * asked the wrong question was never told.
     *
     * All three are the same mistake - a query string naming something that is not there -
     * and all three are answered the same way now. The allow-list is the model's own
     * columns, so it needs no maintenance: `getTableFields()` is read from the schema.
     *
     * @throws InvalidRequestException
     */
    public function applyOrder(QueryOrder $order) {
        Data::debug(get_class($this), "apply order", $order->property, $order->direction);

        $direction = strtolower(trim($order->direction));
        if ($direction != 'asc' && $direction != 'desc') {
            throw new InvalidRequestException("Ordering direction has to be asc or desc, and '{$order->direction}' is not");
        }

        $classes = explode('.', $order->property);
        $field = array_pop($classes);

        /** @var RelationDef[] $relations */
        try {
            $relations = $this->getRelation($classes, true);
        } catch (\Exception $e) {
            throw new InvalidRequestException("Cannot order by '{$order->property}': " . implode('.', $classes) . " is not a relation");
        }

        /** @var Model $related the model the field has to belong to, which is this one unless a relation was named */
        $related = $this;
        $relationNames = [];
        foreach ($relations as $relation) {
            $relationNames[] = $relation->getName();
            $related = $relation->getRelationClass();
        }

        if (!self::isQueryable($related, $field)) {
            throw new InvalidRequestException("Cannot order by '{$order->property}': it is not a field");
        }

        if (count($relationNames) > 0 && $this->relationsFollowRules() && $this->applyOrderThroughRules($relations, $field, $direction))
            return;
        if (count($relationNames) > 0)
            $this->orderByRelated($relationNames, $field, $direction);
        else
            $this->orderBy($field, $direction);
    }


    /**
     * @param QueryInclude $include
     */
    public function applyIncludeOne(QueryInclude $include) {
        if ($this->relationsFollowRules() && $this->includeThroughRules($include)) {
            // Fetched through the related model once the rows are found, in applyIncludeMany()
            return;
        }
        /** @var RelationDef[] $relations */
        $relations = $this->getRelation(explode('.', $include->property), true);
        $relationNames = [];
        foreach ($relations as $relation) {
            if ($relation->getType() != RelationDef::HasOne) return;
            $relationNames[] = $relation->getName();
        }
        if (count($relationNames)) $this->includeRelated($relationNames);
    }

    /**
     * TDDO Support primary key
     * @param Entity $items
     * @param QueryInclude $include
     */
    public function applyIncludeMany(Entity $items, QueryInclude $include) {
        if ($this->relationsFollowRules()) {
            switch ($this->includeThroughRules($include)) {
                case RelationDef::HasOne:
                    $this->applyIncludeOneThroughRules($items, $include);
                    return;
                case RelationDef::HasMany:
                    $this->applyIncludeManyThroughRules($items, $include);
                    return;
            }
        }
        /** @var RelationDef[] $relations */
        $relations = $this->getRelation(explode('.', $include->property), true);
        foreach ($relations as $relation) {
            if ($relation->getType() == RelationDef::HasOne && count($include->queryParser->getIncludes())) {
                $propertyName = $relation->getSimpleName();

                $modelName = $relation->getClass();
                foreach ($items as $item) {
                    if (isset($item->{$propertyName})) {
                        $model = new $modelName();
                        if ($model instanceof ResourceBaseModelInterface) {
                            $queryParser = clone $include->queryParser;
                            $queryParser->parseFilter("id:" . $item->{$propertyName}->id);
                            $item->{$propertyName} = $model->restGet(null, $queryParser)->first();
                        }
                    }

                }
            } else if ($relation->getType() == RelationDef::HasMany) {
                $propertyName = plural($relation->getSimpleName());

                $modelName = $relation->getClass();
                foreach ($items as $item) {
                    $model = new $modelName();

                    if ($model instanceof ResourceBaseModelInterface) {
                        $queryParser = clone $include->queryParser;
                        $queryParser->parseFilter("{$relation->getSimpleOtherField()}.id:{$item->id}");
                        $items = $model->restGet(null, $queryParser);
                        $item->{$propertyName} = $items;
                    }

                }
            }
        }

    }

    /**
     * @param QueryFilter $filter
     */
    public function applyFilter(QueryFilter $filter) {
        Data::debug(get_class($this), "apply", $filter->property, $filter->operator, is_array($filter->value) ? 'array' : $filter->value);

        if ($filter->isRelationFilter()) {

            $classes = explode('.', $filter->property);
            $field = array_pop($classes);
            /** @var RelationDef $relation */
            $relations = [];
            $related = $this;
            try {
                foreach ($this->getRelation($classes, true) as $relation) {
                    $relations[] = $relation->getName();
                    $related = $relation->getRelationClass();
                }
            } catch (\Exception $e) {
                throw new InvalidRequestException("Cannot filter on '{$filter->property}': " . implode('.', $classes) . " is not a relation");
            }
            if (!self::isQueryable($related, $field)) {
                throw new InvalidRequestException("Cannot filter on '{$filter->property}': it is not a field");
            }

            if ($this->relationsFollowRules() && $this->applyFilterThroughRules($filter, $this->getRelation([$classes[0]], true)[0])) {
                return;
            }

            switch ($filter->operator) {
                case Operators::Search:

                    if (is_array($filter->value)) {
                        $this->orGroupStart();
                        foreach ($filter->value as $value)
                            $this->orLikeRelated($relations, $field, $value, 'both', null, true);
                        $this->groupEnd();
                    } else
                        $this->orLikeRelated($relations, $field, $filter->value, 'both', null, true);
                    break;

                case Operators::Not:
                    if (is_array($filter->value))
                        $this->whereNotInRelated($relations, $field, $filter->value);
                    else
                        $this->whereRelated($relations, "{$field} !=", $filter->value);
                    break;
                default:
                    if (is_array($filter->value))
                        $this->whereInRelated($relations, $field, $filter->value);
                    else
                        $this->whereRelated($relations, "{$field} {$filter->operator}", $filter->value);
                    break;
            }

        } else {

            if (!self::isQueryable($this, $filter->property)) {
                throw new InvalidRequestException("Cannot filter on '{$filter->property}': it is not a field");
            }

            switch ($filter->operator) {

                case Operators::Search:
                    if (is_array($filter->value)) {
                        $this->orGroupStart();
                        foreach ($filter->value as $value)
                            $this->orLike($filter->property, $value, 'both', null, true);
                        $this->groupEnd();
                    } else
                        $this->orLike($filter->property, $filter->value, 'both', null, true);
                    break;

                case Operators::Not:
                    if (is_array($filter->value))
                        $this->whereNotIn($filter->property, $filter->value);
                    else
                        $this->where("$filter->property !=", $filter->value);
                    break;

                default:
                    if (is_array($filter->value))
                        $this->whereIn($filter->property, $filter->value);
                    else
                        $this->where("$filter->property $filter->operator", $filter->value);
                    break;
            }

        }

    }


    /**
     * @param QueryField $field
     */
    public function applyField(QueryField $field) {
        if ($field->isRelationField()) {
            // TODO Not yet implemented
        } else {
            if (!self::isQueryable($this, (string) $field->fieldName)) {
                throw new InvalidRequestException("Cannot select '{$field->fieldName}': it is not a field");
            }
            $this->select($field->fieldName);
        }
    }

    /**
     * Whether a query string may name this column - to filter on it, search it, select it or
     * sort by it.
     *
     * A column of the model's table, and not one of its entity's `hiddenFields`: those are kept
     * out of every answer by `toArray()`, and a filter, a search or a sort on one gives it away
     * just the same, a character at a time - `?filter=password~$2y$10$a` answers whether a hash
     * starts that way. Filters and `fields` used to reach the query builder as they were written,
     * any column and any text. A hidden column is refused with the same words as a missing one,
     * so the answer does not say that it exists.
     *
     * @param Model $model
     */
    private static function isQueryable($model, string $field): bool {
        return in_array($field, $model->getTableFields(), true)
            && !in_array($field, self::hiddenFieldsOf($model), true);
    }

    /** @var array<string, string[]> by entity class */
    private static array $hiddenFields = [];

    /**
     * @param Model $model
     * @return string[]
     */
    private static function hiddenFieldsOf($model): array {
        $class = $model->returnType ?? null;
        if (!is_string($class) || !class_exists($class)) {
            return [];
        }
        if (!isset(self::$hiddenFields[$class])) {
            $entity = new $class();
            self::$hiddenFields[$class] = (array) ($entity->hiddenFields ?? []);
        }
        return self::$hiddenFields[$class];
    }

    /**
     * @param Entity $item
     */
    public function applyRestGetOneRelations($item) {
        $ignored = $this->ignoredRestGetOnRelations();
        /** @var RelationDef $relation */
        foreach ($this->getRelations() as $relation) {
            if (in_array($relation->getName(), $ignored)) continue;
            //Data::debug(get_class($this), "running", $relation->getName(), plural($relation->getSimpleName()));
            switch ($relation->getType()) {
                case RelationDef::HasOne:
                    $relationName = $relation->getSimpleName();
                    if (!isset($item->{$relationName})) {
                        $rel = $item->{$relationName};
                        if ($rel instanceof Entity)
                            $rel->find();
                        else
                            Data::debug(get_class($this), "ERROR", $relationName, 'not found for', get_class($item));
                    }
                    break;
                case RelationDef::HasMany:
                    $relationName = plural($relation->getSimpleName());
                    if (!isset($item->{$relationName})) {
                        $rel = $item->{$relationName};
                        if ($rel instanceof Entity)
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

    // <editor-fold desc="Relations through the related model's rules">

    /**
     * Whether a filter, an include or an ordering on a relation asks the related model which of
     * its rows the caller may read, instead of joining its table in past its rules. Off, unless
     * `Config\RestExtension::$relationsFollowRules` is true or the model says so.
     *
     * Joined, `orders?filter=buyer_workspace.name:Gamma` tells a caller who may not read Gamma
     * that Gamma bought something, and `include=buyer_workspace` hands them all of it. Through
     * the rules, a row the caller may not read counts as no row: the filter matches nothing, the
     * include comes back empty, and `buyer_workspace.name:null` matches - as it does for an order
     * with no buyer. With nothing hidden, the answer is the one the join gives, row for row.
     *
     * The rule is the related model's preRestGet(). postRestGet() works on fetched rows, and is
     * asked for an include but not for a filter, just as a count never asks it.
     */
    public function relationsFollowRules(): bool {
        $config = config('RestExtension');
        return (bool)($config->relationsFollowRules ?? false);
    }

    /**
     * How many keys a filter on a relation fetches and writes into the query as a list, before
     * it leaves them as a sub query instead. They are only fetched from a table of up to ten
     * times as many rows; 0 is a sub query every time.
     */
    public function relationKeyListLimit(): int {
        $config = config('RestExtension');
        return (int)($config->relationKeyListLimit ?? 1000);
    }

    /**
     * The values of $column on the rows restGet() would find, matching $filter if there is one,
     * without fetching the rows. Asked of the related model, on a model of its own.
     *
     * @param QueryFilter|null $filter
     * @param string $column
     * @param bool $fetch false for a sub query whatever the size
     * @return KeySet
     */
    public function restKeys(?QueryFilter $filter, string $column, bool $fetch = true): KeySet {
        $parser = new QueryParser();
        if ($filter) {
            $parser->addFilter($filter);
        }
        $table = $this->getTableName();
        $key = $this->db->protectIdentifiers("{$table}.{$column}");

        // Selected first, as for a count, so nothing preRestGet() joins in selects its table
        $this->select("{$key} AS " . KeySet::Alias, false, false);
        $this->preRestGet($parser, null);
        $this->applyRestFilters($parser);

        $builder = $this->builder();
        $everything = is_null($filter);
        foreach (['QBWhere', 'QBJoin', 'QBHaving', 'QBGroupBy'] as $part) {
            if (!empty(KeySet::read($builder, $part))) {
                $everything = false;
            }
        }

        $this->where("{$key} IS NOT NULL", null, false, false);
        // As find() would: tempUseSoftDeletes is off after withDeleted()
        $deletedField = $this->getDeletedField();
        if ($deletedField && $this->tempUseSoftDeletes) {
            $this->where("{$table}.{$deletedField}", null, null, false);
        }

        // A table ten times the list is about as much as is worth reading through for it
        $limit = $fetch ? $this->relationKeyListLimit() : 0;
        if ($limit > 0 && !KeySet::isSmall($this->db, $table, 10 * $limit)) {
            $limit = 0;
        }
        return KeySet::query($builder, $this->db, $limit, $everything);
    }

    /**
     * `relation.field:value` as `key IN (the keys of the related rows that match and may be
     * read)`, in the place the join's condition would have had: after preRestGet(), and among the
     * searches when it is one.
     *
     * @return bool false when the related model cannot be asked, and the join has to do
     */
    private function applyFilterThroughRules(QueryFilter $filter, RelationDef $relation): bool {
        $class = $relation->getClass();
        if (!self::canAnswerThroughRules($class)) {
            return false;
        }
        $link = RelationLink::of($this, $relation);
        if (is_null($link)) {
            return false;
        }

        $null = $filter->operator == Operators::Equal && is_null($filter->value);
        if ($null && $this->relationPathHasNoRules($filter)) {
            // Nothing along the way is hidden from the caller, so the join's answer is the same,
            // and MySQL plans its LEFT JOIN better than any NOT EXISTS
            return false;
        }

        $inner = clone $filter;
        $inner->property = substr($filter->property, strpos($filter->property, '.') + 1);
        $column = $this->db->protectIdentifiers("{$this->getTableName()}.{$link->baseColumn}");
        $matching = LinkedKeys::reach($link, (new $class())->restKeys($inner, $link->relatedColumn), $this->db);

        $search = $filter->operator == Operators::Search;
        if ($null) {
            // What the join's empty row answers: a related row whose field is null, a link to a
            // row that is not there, or no link at all - and now a row that may not be read. That
            // is every row but those linked to a row they may read, without such a link.
            $without = LinkedKeys::reach($link, (new $class())->restKeys(null, $link->relatedColumn, false), $this->db)->except($matching, $this->db);
            if ($link->kind == RelationLink::ThroughTable) {
                $without = $without->except(LinkedKeys::dangling($link, new $class(), $this->db), $this->db);
            }
            // MySQL tries the rows of this table one by one when there are fewer of them than
            // related rows, and works the related rows out once when there are more
            $base = KeySet::tableRows($this->db, $this->getTableName());
            $related = KeySet::tableRows($this->db, (new $class())->getTableName());
            $correlated = is_null($base) || is_null($related) || $base <= $related;
            $condition = $without->in($column, $this->db, true, $correlated);
            if ((!$correlated || !is_null($without->values)) && $link->baseColumn != $this->getPrimaryKey()) {
                // NOT EXISTS is true for a key that is null, as the join's empty row is; NOT IN is not
                $condition = "({$column} IS NULL OR {$condition})";
            }
        } else {
            $condition = $matching->in($column, $this->db, false, $search);
        }

        if ($search) {
            $this->orWhere($condition, null, false, false);
        } else {
            $this->where($condition, null, false, false);
        }
        return true;
    }

    /**
     * Whether no model along a filter's path has a rule for the caller: they may read every row
     * of each, and the relation's join already answers about only those.
     */
    private function relationPathHasNoRules(QueryFilter $filter): bool {
        $classes = explode('.', $filter->property);
        array_pop($classes);
        foreach ($this->getRelation($classes, true) as $relation) {
            $class = $relation->getClass();
            if (self::canAnswerThroughRules($class)) {
                $related = new $class();
                if (!$related->restKeys(null, $related->getPrimaryKey(), false)->everything) {
                    return false;
                }
            }
        }
        return true;
    }

    /**
     * Ordering by a field of a has-one relation sorts a row the caller may not read as if the
     * field were null, so its place in the list does not give the value away.
     *
     * @param RelationDef[] $relations
     * @return bool false when nothing along the way is hidden, and the join's ordering is the same
     */
    private function applyOrderThroughRules(array $relations, string $field, string $direction): bool {
        foreach ($relations as $relation) {
            if ($relation->getType() != RelationDef::HasOne) {
                return false;
            }
        }

        $names = [];
        $guards = [];
        $alias = null;
        foreach ($relations as $relation) {
            $names[] = $relation->getName();
            [$alias] = $this->handleWhereRelated($names);
            $class = $relation->getClass();
            if (self::canAnswerThroughRules($class)) {
                $related = new $class();
                $visible = $related->restKeys(null, $related->getPrimaryKey());
                if (!$visible->everything) {
                    $guards[] = $visible->in($this->db->protectIdentifiers("{$alias}.{$related->getPrimaryKey()}"), $this->db, false, true);
                }
            }
        }
        if (count($guards) == 0) {
            return false;
        }

        $value = $this->db->protectIdentifiers("{$alias}.{$field}");
        $this->orderBy("CASE WHEN " . implode(' AND ', $guards) . " THEN {$value} END", $direction, false, false);
        return true;
    }

    /**
     * How an include is fetched through the rules, by its first relation: HasOne, or HasMany
     * unless it has a limit of its own, which is per row. The rest of the path is the related
     * model's include. Null for what RestExtension already fetches through the related model, one
     * row at a time.
     */
    private function includeThroughRules(QueryInclude $include): ?int {
        $relation = $this->getRelation(explode('.', $include->property), true)[0];
        if (!self::canAnswerThroughRules($relation->getClass())) {
            return null;
        }
        if ($relation->getType() == RelationDef::HasOne) {
            return RelationDef::HasOne;
        }
        $parser = $include->queryParser;
        if (!$parser->hasLimit() && !$parser->hasOffset() && !$parser->isCount()) {
            return RelationDef::HasMany;
        }
        return null;
    }

    /**
     * A has-one include, for every row in one request to the related model: a row it does not
     * find comes back empty, as a missing one does. The rest of the path is its include.
     */
    private function applyIncludeOneThroughRules(Entity $items, QueryInclude $include) {
        $names = explode('.', $include->property);
        $relation = $this->getRelation([$names[0]], true)[0];
        $primaryKey = $this->getPrimaryKey();

        // The rows hold the key already when the relation is theirs; otherwise ask the join
        $owner = null;
        $column = null;
        $link = RelationLink::of($this, $relation);
        if ($link && $link->kind == RelationLink::InBase) {
            $owner = [];
            $column = $link->relatedColumn;
            foreach ($items as $item) {
                $raw = $item->toRawArray();
                if (!array_key_exists($link->baseColumn, $raw)) {
                    // Not selected, with fields=
                    $owner = null;
                    break;
                }
                if (!is_null($raw[$link->baseColumn])) {
                    $owner[(string)$item->{$primaryKey}] = (string)$raw[$link->baseColumn];
                }
            }
        }
        if (is_null($owner)) {
            $owner = [];
            $column = null;
            foreach ($this->relationPairs($relation, $items) as $pair) {
                $owner[$pair[0]] = $owner[$pair[0]] ?? $pair[1];
            }
        }
        $rows = $this->restGetByKeys($relation, array_unique(array_values($owner)), $include->queryParser, array_slice($names, 1), $column);

        $property = $relation->getSimpleName();
        foreach ($items as $item) {
            $key = $owner[(string)$item->{$primaryKey}] ?? null;
            $item->{$property} = !is_null($key) && isset($rows[$key]) ? clone $rows[$key] : self::emptyEntityOf($relation->getClass());
        }
    }

    /**
     * A has-many include, for every row in one request to the related model instead of one per
     * row, in the order that request gives. The rest of the path is its include.
     */
    private function applyIncludeManyThroughRules(Entity $items, QueryInclude $include) {
        $names = explode('.', $include->property);
        $relation = $this->getRelation([$names[0]], true)[0];
        $primaryKey = $this->getPrimaryKey();

        $keys = [];
        foreach ($this->relationPairs($relation, $items) as $pair) {
            $keys[$pair[0]][$pair[1]] = $pair[1];
        }
        $all = [];
        foreach ($keys as $own) {
            foreach ($own as $key) {
                $all[$key] = $key;
            }
        }
        $rows = $this->restGetByKeys($relation, array_values($all), $include->queryParser, array_slice($names, 1));

        $position = array_flip(array_keys($rows));
        $property = plural($relation->getSimpleName());
        foreach ($items as $item) {
            $own = [];
            foreach ($keys[(string)$item->{$primaryKey}] ?? [] as $key) {
                if (isset($position[$key])) {
                    $own[$position[$key]] = $key;
                }
            }
            ksort($own);
            $members = [];
            foreach ($own as $key) {
                $members[] = clone $rows[$key];
            }
            if (count($members) == 0) {
                $item->{$property} = self::emptyEntityOf($relation->getClass());
                continue;
            }
            // As find() gives it: the first row, holding them all
            $collection = clone $members[0];
            foreach ($members as $member) {
                $collection->add($member);
            }
            $item->{$property} = $collection;
        }
    }

    /**
     * [this row's id, the related row's id] for each pair the relation's join makes of $items.
     *
     * @return array[]
     */
    private function relationPairs(RelationDef $relation, Entity $items): array {
        $primaryKey = $this->getPrimaryKey();
        $ids = [];
        foreach ($items as $item) {
            if (!is_null($item->{$primaryKey})) {
                $ids[] = $item->{$primaryKey};
            }
        }
        $pairs = [];
        foreach ((new static())->findRelatedIds([$relation->getName()], $ids) as [$id, $relatedId]) {
            $pairs[] = [(string)$id, (string)$relatedId];
        }
        return $pairs;
    }

    /**
     * The related rows with these keys that the caller may read, through the related model's
     * restGet() with the include's own query, by key in the order it gives.
     *
     * @param string[] $path what is left of the include's path, included in turn
     * @param string|null $column the key's column, the primary key unless another is named
     * @return Entity[]
     */
    private function restGetByKeys(RelationDef $relation, array $keys, QueryParser $queryParser, array $path, ?string $column = null): array {
        if (count($keys) == 0) {
            return [];
        }
        $class = $relation->getClass();
        $related = new $class();
        $parser = clone $queryParser;
        if (count($path)) {
            $parser->parseInclude(implode('.', $path));
        }
        $column = $column ?? $related->getPrimaryKey();
        $filter = new QueryFilter();
        $filter->property = $column;
        $filter->operator = Operators::Equal;
        $filter->value = array_values($keys);
        $parser->addFilter($filter);

        $rows = [];
        foreach ($related->restGet(null, $parser) as $row) {
            $key = (string)$row->{$column};
            $rows[$key] = $rows[$key] ?? $row;
        }
        return $rows;
    }

    /**
     * Whether a model has rules to ask: one without preRestGet() has none, and its join is right.
     */
    private static function canAnswerThroughRules(string $class): bool {
        return is_subclass_of($class, ResourceModelInterface::class) && method_exists($class, 'restKeys');
    }

    /**
     * What a model's find() gives when it finds nothing.
     */
    private static function emptyEntityOf(string $class) {
        $entity = (new $class())->getEntityClass();
        return new $entity();
    }

    // </editor-fold>

}
