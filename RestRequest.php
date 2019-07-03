<?php namespace RestExtension;

use Config\Services;
use RestExtension\Entities\ApiAccessLog;
use RestExtension\Entities\ApiRoute;

/**
 * Class RestRequest
 * @package RestExtension
 */
class RestRequest {

    /**
     * @var RestRequest
     */
    private static $instance;

    public static function getInstance(): RestRequest {
        if(!self::$instance)
            self::$instance = new RestRequest();
        return self::$instance;
    }

    /**
     * @var ApiAccessLog
     */
    public $apiAccessLog;

    /**
     * @var ApiRoute
     */
    public $apiRoute;

    /**
     * @var string
     */
    public $clientId;

    /**
     * @var string
     */
    public $userId;

    /**
     * @var string
     */
    public $accessToken;

    /**
     * @return string|null
     */
    public function getAccessToken() {
        if(!$this->accessToken) {
            $request = Services::request();
            if($request->hasHeader('Authorization'))
                $this->accessToken = substr($request->getHeader('Authorization'), strlen('Authorization: Bearer '));
            else if($request->getGet('access_token'))
                $this->accessToken = $request->getGet('access_token');
        }
        return $this->accessToken;

    }

}
