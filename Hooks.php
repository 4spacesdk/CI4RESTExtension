<?php namespace RestExtension;

use CodeIgniter\Config\Config;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Events\Events;
use Config\Database;
use Config\OrmExtension;
use Config\RestExtension;
use Config\Services;
use DebugTool\Data;
use OrmExtension\ModelParser\ModelParser;
use RestExtension\ApiParser\ApiParser;
use RestExtension\Entities\ApiAccessLog;
use RestExtension\Entities\ApiBlockedLog;
use RestExtension\Entities\ApiErrorLog;
use RestExtension\Entities\ApiRoute;
use RestExtension\Entities\ApiUsageReport;
use RestExtension\Entities\OAuthClient;
use RestExtension\Exceptions\RateLimitExceededException;
use RestExtension\Exceptions\RestException;
use RestExtension\Exceptions\UnauthorizedException;
use RestExtension\Models\ApiAccessLogModel;
use RestExtension\Models\ApiRouteModel;
use RestExtension\Models\ApiUsageReportModel;
use RestExtension\Models\OAuthClientModel;
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
         * Api Routing
         */
        if(self::$config) {

            /*
             * Setup Database connection
             */
            self::$database = Database::connect(self::$config->databaseGroupName ?? 'default');

            $routes = Services::routes(true);

            if(self::$config->enableApiRouting && self::$database->tableExists('api_routes')) {
                $routes = Services::routes(true);
                /** @var ApiRoute $apiRoutes */
                $apiRoutes = (new ApiRouteModel())->find();
                foreach($apiRoutes as $route) {
                    $routes->{$route->method}($route->from, $route->to);
                }
            }

            $isLocalIP = in_array(Services::request()->getIPAddress(), ['127.0.0.1', '::1']);

            /*
             * Export TypeScript Models
             */
            if(isset(self::$config->typescriptModelExporterRoute) && self::$config->typescriptModelExporterRoute
                && $isLocalIP) {
                $routes->get(self::$config->typescriptModelExporterRoute, function($debug = false) {
                    $parser = ModelParser::run();
                    $parser->generateTypeScript($debug);

                    if($debug) return;

                    // Zip models folder
                    shell_exec('cd "' . WRITEPATH . 'tmp/" && zip -r models.zip models');
                    $path = WRITEPATH . 'tmp/models.zip';

                    header("Content-type: application/zip");
                    header("Content-Disposition: attachment; filename=$path");
                    header("Content-length: " . filesize($path));
                    header("Pragma: no-cache");
                    header("Expires: 0");
                    readfile("$path");

                    exit(0);
                });
            }

            /*
             * Export TypeScript API Class
             */
            if(isset(self::$config->typescriptAPIExporterRoute) && self::$config->typescriptAPIExporterRoute
                && $isLocalIP) {
                $routes->get(self::$config->typescriptAPIExporterRoute, function($debug = 0) {
                    $parser = ApiParser::run();
                    $parser->generateTypeScript($debug);

                    // Zip api folder
                    $path = WRITEPATH . 'tmp/Api.ts';

                    header("Content-type: application/x-typescript");
                    header("Content-Disposition: attachment; filename=$path");
                    header("Content-length: " . filesize($path));
                    header("Pragma: no-cache");
                    header("Expires: 0");
                    readfile("$path");

                    exit(0);
                });
            }

            /*
             * Export Xamarin Models
             */
            if(isset(self::$config->xamarinModelExporterRoute) && self::$config->xamarinModelExporterRoute
                && $isLocalIP) {
                $routes->get(self::$config->xamarinModelExporterRoute, function($debug = false) {
                    $parser = ModelParser::run();
                    $parser->generateXamarin($debug);

                    if($debug) return;

                    // Zip models folder
                    shell_exec('cd "' . WRITEPATH . 'tmp/xamarin/" && zip -r models.zip models');
                    $path = WRITEPATH . 'tmp/xamarin/models.zip';

                    header("Content-type: application/zip");
                    header("Content-Disposition: attachment; filename=$path");
                    header("Content-length: " . filesize($path));
                    header("Pragma: no-cache");
                    header("Expires: 0");
                    readfile("$path");

                    exit(0);
                });
            }

            /*
             * Export Xamarin API Class
             */
            if(isset(self::$config->xamarinAPIExporterRoute) && self::$config->xamarinAPIExporterRoute
                && isset(self::$config->xamarinAPINamespace) && self::$config->xamarinAPINamespace
                && $isLocalIP) {
                $routes->get(self::$config->xamarinAPIExporterRoute, function($debug = false) {
                    $parser = ApiParser::run();
                    $parser->generateXamarin($debug);

                    // Zip api folder
                    $path = WRITEPATH . 'tmp/Api.cs';

                    header("Content-type: application/x-typescript");
                    header("Content-Disposition: attachment; filename=$path");
                    header("Content-length: " . filesize($path));
                    header("Pragma: no-cache");
                    header("Expires: 0");
                    readfile("$path");

                    exit(0);
                });
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
                    ->groupStart()
                        ->where('from', $routeFrom)
                        ->orWhere('from', '/'.$routeFrom)
                    ->groupEnd()
                    ->where('method', $request->getMethod())
                    ->find();
                if(!$apiRoute->exists() && !$request->isCLI()) {
                    throw new \Exception("RestExtension: Route ($routeFrom) not found. Api Routes have to be store in the ".
                        "database to check against scopes.");
                }
                $restRequest->apiRoute = $apiRoute;

                /*
                 * Authorize
                 */
                $authResponse = self::$config->authorize($request, $restRequest->apiRoute->scope);

                /*
                 * Public API route can skip authorization
                 */
                if(!$apiRoute->is_public) {

                    if(!isset($authResponse->authorized) || $authResponse->authorized == false) {

                        /*
                         * Unauthorized!
                         */
                        throw new UnauthorizedException($authResponse->reason);
                    }
                }

                if($authResponse && isset($authResponse->client_id)) {

                    /*
                     * Authorized, go on
                     */
                    $restRequest->clientId = $authResponse->client_id;
                    $restRequest->userId = $authResponse->user_id;

                    /*
                     * API Rate Limit
                     */
                    if(self::$config->enableRateLimit && self::$database->tableExists('api_access_logs')) {
                        /** @var OAuthClient $oauthClient */
                        $oauthClient = (new OAuthClientModel())
                            ->where('client_id', $authResponse->client_id)
                            ->find();
                        if($oauthClient->rate_limit > 0) {
                            $lastHour = (new ApiAccessLogModel())
                                ->where('client_id', $authResponse->client_id)
                                ->where('date >', date('Y-m-d H:i:s', strtotime('-1 hour')))
                                ->countAllResults();
                            if($lastHour >= $oauthClient->rate_limit) {

                                /*
                                 * Unauthorized!
                                 */
                                throw new RateLimitExceededException('API Rate limit exceeded');
                            }

                        }
                    }

                    /*
                     * API Usage Reporting
                     */
                    if(self::$config->enableUsageReporting && self::$database->tableExists('api_usage_reports')) {

                        /** @var ApiUsageReport $usageReport */
                        $usageReport = (new ApiUsageReportModel())
                            ->where('client_id', $authResponse->client_id)
                            ->where('date', date('Y-m-d'))
                            ->find();
                        if(!$usageReport->exists()) {
                            $usageReport->client_id = $authResponse->client_id;
                            $usageReport->date = date('Y-m-d');
                            $usageReport->usage = 0;
                        }
                        $usageReport->usage++;
                        $usageReport->save();

                    }
                }
            }

            /*
             * API Access Log
             */
            if(self::$config->enableAccessLog && self::$database->tableExists('api_access_logs')) {

                $apiAccessLog = new ApiAccessLog();
                if($restRequest->userId) $apiAccessLog->user_id = $restRequest->userId;
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
                if($apiAccessLog) {
                    $apiAccessLog->milliseconds = timer()->getElapsedTime('RestExtension::timer') * 1000;
                    $apiAccessLog->save();
                }
            }

        }
    }

    public static function exceptionHandler(Throwable $exception) {
        if(self::$config) {

            $request = Services::request();
            $restRequest = RestRequest::getInstance();

            if(self::$config->enableErrorLog && self::$database->tableExists('api_error_logs')) {

                $apiErrorLog = new ApiErrorLog();
                if($restRequest->userId) $apiErrorLog->user_id = $restRequest->userId;
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

            if($exception instanceof UnauthorizedException || $exception instanceof RateLimitExceededException) {

                if(self::$config->enableBlockedLog && self::$database->tableExists('api_blocked_logs')) {
                    $apiBlockedLog = new ApiBlockedLog();
                    if($restRequest->userId) $apiBlockedLog->user_id = $restRequest->userId;
                    if($restRequest->clientId) $apiBlockedLog->client_id = $restRequest->clientId;
                    if($restRequest->apiRoute) $apiBlockedLog->api_route_id = $restRequest->apiRoute->id;
                    $apiBlockedLog->access_token = $restRequest->getAccessToken();
                    $apiBlockedLog->uri = current_url();
                    $apiBlockedLog->date = date('Y-m-d H:i:s');
                    $apiBlockedLog->reason = $exception->getMessage();
                    $apiBlockedLog->ip_address = $request->getIPAddress();
                    $apiBlockedLog->save();
                }

            }

            if($exception instanceof RestException) {

                $response = Services::response();
                $response->setStatusCode($exception->getCode());
                $response->setJSON([
                    'status'    => 'ERROR',
                    'code'      => $exception->getCode(),
                    'error'     => get_class($exception),
                    'reason'    => $exception->getMessage(),
                    'debug'     => Data::getDebugger()
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
