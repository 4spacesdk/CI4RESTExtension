<?php namespace RestExtension;

use CodeIgniter\Config\Config;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Events\Events;
use Config\Database;
use Config\OrmExtension;
use Config\RestExtension;
use Config\Services;
use DebugTool\Data;
use RestExtension\Entities\ApiAccessLog;
use RestExtension\Entities\ApiBlockedLog;
use RestExtension\Entities\ApiErrorLog;
use RestExtension\Entities\ApiRoute;
use RestExtension\Exceptions\UnauthorizedException;
use RestExtension\Models\ApiRouteModel;
use Throwable;

/**
 * Class PreSystem
 * @package RestExtension\Hooks
 */
class Hooks {

    /**
     * @var RestExtension
     */
    private static $config;

    /**
     * @var callable
     */
    private static $ciExceptionHandler;

    /**
     * @var BaseConnection
     */
    private static $database;

    /**
     * PreSystem Hook
     * You have to add this hook yourself in app/Config/Events.php
     */
    public static function preSystem() {

        /*
         * Start timer for benchmarking
         */
        timer('RestExtension::timer');

        /*
         * Add additional hooks
         */
        Events::on('post_controller_constructor', [\RestExtension\Hooks::class, 'postControllerConstructor']);
        Events::on('post_system', [\RestExtension\Hooks::class, 'postSystem']);

        /*
         * Append RestExtension Entities and Models to OrmExtension namespaces
         */
        if(!is_array(OrmExtension::$modelNamespace))
            OrmExtension::$modelNamespace = [OrmExtension::$modelNamespace];
        if(!is_array(OrmExtension::$entityNamespace))
            OrmExtension::$entityNamespace = [OrmExtension::$entityNamespace];
        OrmExtension::$modelNamespace[] = 'RestExtension\Models\\';
        OrmExtension::$entityNamespace[] = 'RestExtension\Entities\\';

        /*
         * Fetch Config file
         */
        self::$config = Config::get('RestExtension');

        /*
         * Setup Database connection
         */
        self::$database = Database::connect(self::$config->databaseGroupName ?? 'default');

        /*
         * Api Routing
         */
        if(self::$config) {

            if(self::$config->enableApiRouting && self::$database->tableExists('api_routes')) {
                $routes = Services::routes(true);
                /** @var ApiRoute $apiRoutes */
                $apiRoutes = (new ApiRouteModel())->find();
                foreach($apiRoutes as $route) {
                    $routes->{$route->method}($route->from, $route->to);
                }
            }
            
        }

        /*
         * Error & Exception handlers
         */
        self::$ciExceptionHandler = set_exception_handler([Hooks::class, 'exceptionHandler']);

    }

    /**
     * @throws \Exception
     */
    public static function postControllerConstructor() {
        if(self::$config) {

            $restRequest = RestRequest::getInstance();
            $request = Services::request();

            /*
             * CLI is trusted
             */
            if($request->isCLI())
                return;

            if(self::$config->enableApiRouting && self::$database->tableExists('api_routes')) {

                /*
                 * Search for api route based on CI's matched route
                 */
                $routeFrom = Services::router()->getMatchedRoute()[0];
                /** @var ApiRoute $apiRoute */
                $apiRoute = (new ApiRouteModel())
                    ->where('from', $routeFrom)
                    ->find();
                if(!$apiRoute->exists() && !$request->isCLI()) {
                    throw new \Exception("RestExtension: Route ($routeFrom) not found. Api Routes have to be store in the ".
                        "database to check against scopes.");
                }
                $restRequest->apiRoute = $apiRoute;

                /*
                 * Public API route can skip authorization
                 */
                if(!$apiRoute->is_public) {

                    /*
                     * Authorize
                     */
                    $authResponse = self::$config->authorize($request, $restRequest->apiRoute->scope);

                    if(!$authResponse->authorized) {

                        /*
                         * Unauthorized!
                         */
                        throw new UnauthorizedException($authResponse->reason);
                    }

                    /*
                     * Authorized, go on
                     */
                }
            }

            if(self::$config->enableAccessLog && self::$database->tableExists('api_access_logs')) {

                $apiAccessLog = new ApiAccessLog();
                if($restRequest->clientId) $apiAccessLog->client_id = $restRequest->clientId;
                if($restRequest->apiRoute) $apiAccessLog->api_route_id = $restRequest->apiRoute->id;
                $apiAccessLog->access_token = $restRequest->getAccessToken();
                $apiAccessLog->uri = current_url();
                $apiAccessLog->date = date('Y-m-d H:i:s');
                $apiAccessLog->ip_address = $request->getIPAddress();
                $apiAccessLog->save();

                $restRequest->apiAccessLog = $apiAccessLog;
            }

        }
    }

    public static function postSystem() {

        /*
         * CLI is trusted
         */
        if(Services::request()->isCLI())
            return;

        /*
         * Stop timer for benchmarking
         */
        timer('RestExtension::timer');

        if(self::$config) {

            if(self::$config->enableAccessLog && self::$database->tableExists('api_access_logs')) {
                $apiAccessLog = RestRequest::getInstance()->apiAccessLog;
                $apiAccessLog->milliseconds = timer()->getElapsedTime('RestExtension::timer') * 1000;
                $apiAccessLog->save();
            }

        }
    }

    public static function exceptionHandler(Throwable $exception) {
        if(self::$config) {

            $request = Services::request();
            $restRequest = RestRequest::getInstance();

            if(self::$config->enableErrorLog && self::$database->tableExists('api_error_logs')) {

                $apiErrorLog = new ApiErrorLog();
                if($restRequest->clientId) $apiErrorLog->client_id = $restRequest->clientId;
                if($restRequest->apiRoute) $apiErrorLog->api_route_id = $restRequest->apiRoute->id;
                $apiErrorLog->access_token = $restRequest->getAccessToken();
                $apiErrorLog->uri = current_url();
                $apiErrorLog->date = date('Y-m-d H:i:s');
                $apiErrorLog->code = $exception->getCode();
                $apiErrorLog->message = $exception->getMessage();
                $apiErrorLog->ip_address = $request->getIPAddress();
                $apiErrorLog->headers = json_encode($request->getHeaders(), JSON_PRETTY_PRINT);
                $apiErrorLog->save();
            }

            if($exception instanceof UnauthorizedException) {

                if(self::$config->enableBlockedLog && self::$database->tableExists('api_blocked_logs')) {
                    $apiBlockedLog = new ApiBlockedLog();
                    if($restRequest->clientId) $apiBlockedLog->client_id = $restRequest->clientId;
                    if($restRequest->apiRoute) $apiBlockedLog->api_route_id = $restRequest->apiRoute->id;
                    $apiBlockedLog->access_token = $restRequest->getAccessToken();
                    $apiBlockedLog->uri = current_url();
                    $apiBlockedLog->date = date('Y-m-d H:i:s');
                    $apiBlockedLog->reason = $exception->getMessage();
                    $apiBlockedLog->ip_address = $request->getIPAddress();
                    $apiBlockedLog->save();
                }

                $response = Services::response();
                $response->setStatusCode('401');
                $response->setJSON([
                    'status' => 'ERROR',
                    'code' => 401,
                    'error' => 'Unauthorized'
                ]);
                $response->send();
                return;
            }

        }

        /*
         * Execute CodeIgniter Exception Handler
         */
        call_user_func(self::$ciExceptionHandler, $exception);
    }

}
