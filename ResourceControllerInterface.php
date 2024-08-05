<?php namespace RestExtension;
/**
 * Created by PhpStorm.
 * User: martin
 * Date: 2018-12-05
 * Time: 13:38
 */
interface ResourceControllerInterface {

    public function _getResourceName() : string;
    public function get($id = 0);
    public function post();
    public function put($id = 0);
    public function patch($id = 0);
    public function delete($id);

    public function _setResources($items);
    public function _setResource($item);

    public function error($errorCode, $statusCode = 503);

}
