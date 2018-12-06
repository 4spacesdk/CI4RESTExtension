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
     * @param QueryParser $queryParser
     * @param $id
     */
    public function preRestGet($queryParser, $id);

    /**
     * @param QueryParser $queryParser
     * @param Entity $items
     */
    public function postRestGet($queryParser, $items);

    /**
     * @param Entity $item
     * @return boolean
     */
    public function isRestCreationAllowed($item): bool;

    /**
     * @param Entity $item
     * @return boolean
     */
    public function isRestUpdateAllowed($item): bool;

    /**
     * @param Entity $item
     * @return boolean
     */
    public function isRestDeleteAllowed($item): bool;

    /**
     * @param Entity $items
     */
    public function appleRestGetManyRelations($items);

}