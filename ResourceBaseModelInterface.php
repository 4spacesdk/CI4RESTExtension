<?php namespace RestExtension;

use OrmExtension\Extensions\Entity;
use RestExtension\Filter\QueryFilter;
use RestExtension\Includes\QueryInclude;
use RestExtension\Ordering\QueryOrder;
use RestExtension\QueryParser;

/**
 * Created by PhpStorm.
 * User: martin
 * Date: 2018-12-05
 * Time: 13:16
 */
interface ResourceBaseModelInterface {

    /**
     * @param $id
     * @param QueryParser $queryParser
     * @return Entity
     */
    public function restGet($id, $queryParser);

    /**
     * @param QueryInclude $include
     */
    public function applyIncludeOne(QueryInclude $include);

    /**
     * @param Entity $items
     * @param QueryInclude $include
     */
    public function applyIncludeMany(Entity $items, QueryInclude $include);

    /**
     * @param QueryFilter $filter
     */
    public function applyFilter(QueryFilter $filter);

    /**
     * @param QueryOrder $order
     */
    public function applyOrder(QueryOrder $order);

    /**
     * @param Entity $item
     */
    public function applyRestGetOneRelations($item);

    /**
     * @return string[]
     */
    public function ignoredRestGetOnRelations();

}