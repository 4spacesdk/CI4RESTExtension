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
interface ResourceBaseModelInterface {

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

}