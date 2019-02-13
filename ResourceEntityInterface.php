<?php namespace RestExtension;
use OrmExtension\Extensions\Entity;

/**
 * Created by PhpStorm.
 * User: martin
 * Date: 2018-12-05
 * Time: 14:04
 */
interface ResourceEntityInterface {

    /**
     * @param array $data
     * @return ResourceEntityInterface|Entity
     */
    public static function post($data);

    /**
     * @param int $id
     * @param array $data
     * @return ResourceEntityInterface|Entity
     */
    public static function put($id, $data);

    /**
     * @param int $id
     * @param array $data
     * @return ResourceEntityInterface|Entity
     */
    public static function patch($id, $data);

    /**
     * @return string[]
     */
    public function getPopulateIgnore();

    /**
     * @param array $data
     */
    public function populatePut($data);

    /**
     * @param array $data
     * @return bool
     */
    public function populatePatch($data);

    /**
     * @param ResourceEntityInterface|Entity $relation
     */
    public function relationAdded($relation);
}