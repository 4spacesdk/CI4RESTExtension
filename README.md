# CI4RESTExtension
RESTExtension for CodeIgniter 4

Should be used in coherence with [ORMExtension](https://github.com/4spacesdk/CI4OrmExtension).

## Enable API Management 
If used in coherence with [AuthExtension](https://github.com/4spacesdk/CI4AuthExtension) you can take advantage of more API Features like Access Log and Scope Authorization.
#### Step 1) 
Install [AuthExtension](https://github.com/4spacesdk/CI4AuthExtension).
#### Step 2)
Add Config file `RestExtension.php`.
```php
<?php namespace Config;

use CodeIgniter\Config\BaseConfig;
use CodeIgniter\HTTP\Request;
use RestExtension\AuthorizeResponse;

class RestExtension extends BaseConfig {

    /*
     * Enabling this feature requires you to
     * - Store routes in the database
     * - Assign scopes to the routes
     * This will allow RestExtension to authorize every request against OAuth2 Scopes
     */
    public $enableApiRouting        = FALSE;

    /*
     * Track every access to the API.
     * Consider adding a CronJob to periodically cleanup this table
     */
    public $enableAccessLog         = FALSE;

    /*
     * Track blocked requests.
     * Ex. if you use scopes to authorize
     */
    public $enableBlockedLog        = FALSE;

    /*
     * Track errors
     */
    public $enableErrorLog          = FALSE;

    /*
     * Enable rate limit
     */
    public $enableRateLimit         = TRUE;

    /*
     * Hourly rate limit
     * Requires enableAccessLog to be TRUE
     */
    public $defaultRateLimit        = 0;

    /*
     * Daily usage report
     */
    public $enableUsageReporting    = FALSE;


    /**
     * Apply function to authenticate $request.
     * access_token is placed in either a header called Authorization or a GET-parameter called access_token
     * @param Request $request
     * @param string $scope
     * @return object
     */
    public function authorize(Request $request, $scope = null) {

        /**
         * If AuthExtension is part of this project you could do something like
         */
        return (object)\AuthExtension\AuthExtension::authorize($scope);


        /**
         * If AuthExtension is part of another project (ex. Micro Service) you could do something like
         */

//        $url = \CodeIgniter\Config\Config::get('Domains')->auth.'/check?';
//        $url .= http_build_query(['scope' => $scope, 'access_token' => RestRequest::getInstance()->getAccessToken()]);
//
//        $ch = curl_init();
//        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
//        curl_setopt($ch, CURLOPT_URL, $url);
//        $json = json_decode(curl_exec($ch));
//        curl_close($ch);
//        return $json;
    }

    /*
     * Provide Route for Typescript model exporter
     */
    public $typescriptModelExporterRoute    = 'export/models';

    /*
     * Provide Route for Typescript API exporter
     */
    public $typescriptAPIExporterRoute      = 'export/api/ts';

    /*
     * Provide Route for Typescript API exporter
     */
    public $vueAPIExporterRoute             = 'export/api/vue';

    /*
     * Provide Route for Xamarin model exporter
     */
    public $xamarinModelExporterRoute       = 'xamarin/models';

    /*
     * Provide Namespace for Xamarin API
     */
    public $xamarinAPINamespace             = 'App.Http';
    public $xamarinBaseAPINamespace         = 'App.Http';

    /*
     * Provide Route for Xamarin API exporter
     */
    public $xamarinAPIExporterRoute         = 'xamarin/api';

    /*
     * Provide Namespace for Api request and response Interfaces
     */
    public $apiInterfaceNamespace           = 'App\Interfaces';

    /*
     * Provide base namespace for Controllers to be used in Api export
     */
    public $apiControllerNamespace          = 'App\Controllers';

    /*
     * Provide destination for TypeScript Models to be placed when executed as Command
     */
    public $typescriptModelExporterDestination  = '~/Desktop/ModelExporter';

    /*
     * Provide destination for TypeScript API to be placed when executed as Command
     */
    public $typescriptAPIExporterDestination    = '~/Desktop/APIExporter';

}
```

### Step 3)
Add this line to `Config/Events.php` `Events::on('pre_system', [\RestExtension\Hooks::class, 'preSystem']);`. Must be places below `OrmExtension`.

### Step 4)
Add this line to your migration file `\RestExtension\Migration\Setup::migrateUp();` and run it.

## Query Parser
Add these lines to your base controller:
```php
$this->queryParser = new QueryParser();
$this->queryParser->parseRequest($this->request);
```
If you use OrmExtension add this trait to your base model: `use OrmExtensionModelTrait;`. 
This will allow RestExtension to filter your resources based on your models and their relations.

### Examples
`/milestones?filter=name:[Milepæl%202,Milepæl%201],project.id:9`   
Filtering milestones by name either "Milepæl 02" or "Milepæl 01" and related to project with id 9.  

`/projects?filter=id:>=10,id:<=100,name:"SOME PROJECT"`  
Filtering projects by id greater than or equal to 10 and lower than or equal to 100 and name equals "SOME PROJECT". 

### Filtering

RESTExtension uses the RFS filter method

     * Example
     * GET /items?filter=price:>=10,price:<=100.
     * This will filter items with price greater than or equal to 10 AND lower than or equal to 100. (>=10 & <=100)
     *
     * Filters are made up of rules, with a property, a value and an operator. Where property is the field you want to
     * filter against, e.g. featured, value is what you want to match e.g. true and operator is most commonly 'equals' e.g. :.
     *
     * When specifying a filter, the property and value are always separated by a colon :.
     * If no other operator is provided, then this is a simple 'equals' comparison.
     *
     * featured:true - return all posts where the featured property is equal to true.
     *
     * You can also do a 'not equals' query, by adding the 'not' operator - after the colon.
     * For example, if you wanted to find all posts which have an image,
     * you could look for all posts where image is not null: feature_image:-null.
     *
     * Filters with multiple rules
     * You can combine rules using either 'and' or 'or'. If you'd like to find all posts that are either featured,
     * or they have an image, then you can combine these two rules with a comma , which represents 'or':
     * filter=featured:true,feature_image:-null.
     *
     * If you're looking for all published posts which are not static pages,
     * then you can combine the two rules with a plus + which represents 'and': filter=status:published+page:false.
     * This is the default query performed by the posts endpoint.
     *
     * Syntax Reference
     * A filter expression is a string which provides the property, operator and value in the form property:operatorvalue:
     *
     *  -  property - a path representing the key to filter on
     *  -  : - separator between property and an operator-value expression
     *      -  operator is optional, so : on its own is roughly =
     *
     * Property
     * Matches: [a-zA-Z_][a-zA-Z0-9_.]
     *
     *  - can contain only alpha-numeric characters and _
     *  - cannot contain whitespace
     *  - must start with a letter
     *  - supports . separated paths, E.g. authors.slug or posts.count
     *  - is always lowercase, but accepts and converts uppercase
     *
     * Value
     * Can be one of the following
     *
     *  - null
     *  - true
     *  - false
     *  - a _number _(integer)
     *  - a literal
     *       - Any character string which follows these rules:
     *       - Cannot start with - but may contain it
     *       - Cannot contain any of these symbols: '"+,()><=[] unless they are escaped
     *       - Cannot contain whitespace
     *  - a string
     *       - ' string here ' Any character except a single or double quote surrounded by single quotes
     *       - Single or Double quote _MUST _be escaped*
     *       - Can contain whitespace
     *       - A string can contain a date any format that can be understood by new Date()
     *
     * Operators
     *  -   not operator
     *  >   greater than operator
     *  >=  greater than or equals operator
     *  <   less than operator
     *  <=  less than or equals operator
     *  ~   search, this will do an case insensitive like with % on both sides
     *
     * Combinations
     *  + - represents and OBS! Not supported
     *  , - represents or
     *  ( filter expression ) - overrides operator precedence OBS! Not supported
     *  [] - grouping for IN style, ex. tags:[first-tag,second-tag]
     
     
### Ordering

`ordering=PROPERTY:DIRECTION`  
Multiple ordering can by applied by `,` separation.  
Ex. `?ordering=title:asc,created:desc`

Ordering by relations:  
Ex. `?ordering=created_by.first_name:asc`   
Use `.` separation for deep relations. NB, relation names is always singular  

Direction is optional.
     
     
### Include

`include=RELATION`  
Ex. `?include=created_by`  
Ex. `?include=created_by.role`     
Use `.` separation for deep relations. NB, relation names is always singular


### API Parser
If you document your API endpoints like this
```
/**
 * @route /loads/{loadId}/calculate
 * @method get
 * @custom true
 * @param int $loadId parameterType=path
 * @parameter int[] $user_ids parameterType=query required=true
 * @parameter string $start parameterType=query required=true
 * @parameter string $end parameterType=query required=true
 */
```
RestExtension can generate Swagger documentation for you.
```php
$parser = ApiParser::run();
$paths = $parser->generateSwagger();
```
Attach `$paths` to swagger paths.  

#### Explanation
* `@route` Is self explained.  
* `@method` Is self explained.
* `@custom` If not present, RestExtension will add the default parameters: filter, include, offset, limit, ordering
* `@param` & `@parameter` Is the same. Starts with the type followed by the name. You can specify parameterType and requirement.  
