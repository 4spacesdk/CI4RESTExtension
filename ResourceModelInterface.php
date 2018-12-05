<?php namespace RestExtension;

use OrmExtension\Extensions\Entity;
use RestExtension\Filter\QueryFilter;
use RestExtension\Filter\QueryParser;

/**
 * Created by PhpStorm.
 * User: martin
 * Date: 2018-12-05
 * Time: 13:16
 */
interface ResourceModelInterface {

    /**
     * @param $id
     * @param QueryParser $queryParser
     * @return Entity
     */
    public function restGet($id, $queryParser);

    /**
     * @param QueryFilter $filter
     */
    public function apply(QueryFilter $filter);

    /**
     * @param Entity $item
     */
    public function applyRestGetOneRelations($item);

    /**
     * @param Entity $item
     * @return boolean
     */
    public function isRestDeleteAllowed($item): bool;

    /**
     * @param QueryParser $request
     * @param $id
     */
    public function applyRestGetFilter($request, $id);

    /**
     * @param Entity $items
     */
    public function appleRestGetManyRelations($items);

}