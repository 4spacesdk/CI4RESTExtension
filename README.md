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
     * Consider adding a CronJob to periodically cleanup this table - see "Log tables" below
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
     * Headers the error log writes as [redacted], on top of Authorization, Proxy-Authorization,
     * Cookie and Set-Cookie, which always are. Compared without regard to case.
     */
    public array $redactedHeaders   = [];

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

    /*
     * Filters, includes and orderings on a relation go through the related model's own rules
     * (its preRestGet), instead of joining its table in past them. See "Relations and rules" below.
     */
    public $relationsFollowRules    = FALSE;

    /*
     * With relationsFollowRules: how many keys of the related rows are fetched into the query
     * as a list, before they stay a sub query. 0 is a sub query every time.
     */
    public $relationKeyListLimit    = 1000;

    /*
     * Writes through the resource controller go through the same rules as a read. See "Writes
     * and rules" below.
     */
    public $writesFollowRules       = FALSE;


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

//        $url = config('Domains')->auth.'/check?';
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
    public $xamarinAPIClassName             = 'Api';
    public $xamarinAPINamespace             = 'App.Http';
    public $xamarinBaseAPINamespace         = 'App.Http';

    /*
     * Provide Route for Xamarin API exporter
     */
    public $xamarinAPIExporterRoute         = 'xamarin/api';

    /*
     * Provide Namespace for Api request and response Interfaces. A list exports the interfaces of
     * every namespace in it - a composer package's with the app's; the first wins a name both have.
     */
    public $apiInterfaceNamespace           = 'App\Interfaces';

    /*
     * Provide base namespace for Controllers to be used in Api export. A list exports the
     * controllers of every namespace in it, as for the interfaces.
     */
    public $apiControllerNamespace          = 'App\Controllers';

    /*
     * The base classes of resource controllers, a class or a list
     */
    public $resourceControllerClass         = 'App\Core\ResourceController';

    /*
     * Provide destination for TypeScript Models to be placed when executed as Command
     */
    public $typescriptModelExporterDestination  = '~/Desktop/ModelExporter';

    /*
     * Provide destination for TypeScript API to be placed when executed as Command
     */
    public $typescriptAPIExporterDestination    = '~/Desktop/APIExporter';

    /*
     * Where the TypeScript Api.ts imports BaseApi and the models from
     */
    public $typescriptBaseApiImportPath     = '@app/core/http/Api/BaseApi';
    public $typescriptModelsImportPath      = '@app/core/models';

    /*
     * Also generate find$(), count$(), save$() and delete$(), which return a typed Observable
     * instead of taking a callback. BaseApi has to provide executeFind$() and friends; the
     * generated one below does.
     */
    public $typescriptObservableMethods     = false;

    /*
     * Let api:export write BaseApi.ts, ApiFilter.ts, ApiInclude.ts and ApiOrdering.ts next to
     * Api.ts, and model:export write BaseModel.ts next to the models, replacing what is there.
     * Leave it off when the application has written its own.
     */
    public $typescriptAPIExportBaseClasses  = false;

}
```

### TypeScript client for Angular
With `typescriptAPIExportBaseClasses` on, `php spark model:export` and `php spark api:export` produce
a complete client for Angular's HttpClient that compiles under `strict` and `noImplicitOverride`.
Endpoints are created with `new`, so hand the injector over once at startup:

```ts
provideHttpClient(),
provideAppInitializer(() => {
    ApiConfig.injector = inject(Injector);
    ApiConfig.baseUrl = '/api';
}),
```

With `typescriptObservableMethods` on as well, a request can feed a signal directly:

```ts
projects = rxResource({stream: () => Api.projects().get().whereEquals('name', 'x').find$()});
```

### Step 3)
Add this line to `Config/Events.php` `Events::on('pre_system', [\RestExtension\Hooks::class, 'preSystem']);`. Must be places below `OrmExtension`.
Add this line to `Config/Events.php` `Events::on('pre_command', [\RestExtension\Hooks::class, 'preSystem']);`. Must be places below `OrmExtension`.

### Step 4)
Add this line to your migration file `\RestExtension\Migration\Setup::migrateUp();` and run it.

## Query Parser
Add these lines to your base controller:
```php
$this->queryParser = new QueryParser();
$this->queryParser->parseRequest($this->request);
```
If you use OrmExtension add this trait to your base model: `use RestExtension\ResourceModelTrait;` or extend `RestExtension\Core\Model`. 
This will allow RestExtension to filter your resources based on your models and their relations.

### Examples
`/milestones?filter=name:[Milepæl%202,Milepæl%201],project.id:9`   
Filtering milestones by name either "Milepæl 02" or "Milepæl 01" and related to project with id 9.  

`/projects?filter=id:>=10,id:<=100,name:"SOME PROJECT"`  
Filtering projects by id greater than or equal to 10 and lower than or equal to 100 and name equals "SOME PROJECT". 

### Log tables

The access, error and blocked logs never store the access token, and the error log writes the
value of every header that carries a credential as `[redacted]` (see `$redactedHeaders`). Nothing
removes old rows; schedule this from the application, e.g. nightly:

```php
\RestExtension\Logs::PruneOlderThan(30); // days - returns how many rows went from each table
```

### What a query may name

A filter, a search, `fields` and `ordering` may name a column of the model's table, or of a related
model's, and nothing else - and none of the entity's `hiddenFields`, which are kept out of every
answer and would otherwise be readable through a filter or a sort. Anything else is an
`InvalidRequestException`, with the same words for a hidden column as for one that is not there.

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


### Relations and rules

By default a filter, an include or an ordering on a relation joins the related table in, past the
related model's rules. `orders?filter=buyer_workspace.name:Gamma` then tells a caller who may not
read Gamma that Gamma bought something, and `include=buyer_workspace` hands them all of Gamma.

With `relationsFollowRules` on - in `Config\RestExtension`, or per model by overriding
`relationsFollowRules()` - each related model is asked, through its `preRestGet()`, which of its
rows the caller may read, along the whole path (`order_line.product.workspace.name`), and a row
they may not read counts as no row:

* a filter only matches through rows the caller may read, and `relation.field:null` matches a
  row with no such related row, as it does a row with no related row at all;
* a has-one include the caller may not read comes back empty, and a has-many include holds only
  what they may read - fetched for all rows in one request, instead of one per row;
* ordering by a has-one relation's field sorts a row the caller may not read as if the field
  were empty.

Where nothing is hidden from the caller, the answer is the one the join gives, row for row, page
for page. The rule is `preRestGet()`: `postRestGet()` works on fetched rows, so it decides what an
include holds but not what a filter matches - as it never decided a count.

What it costs: the related rows' keys are asked for on their own, without their rows, and used in
the query as a list when there are at most `relationKeyListLimit` of them and their table is at
most ten times that size; otherwise as a sub query MySQL can plan from either side. On a CRM of
400 000 activities, 100 000 contacts and 60 000 deals, a page of 25 and its count took about as
long as the join for an admin, and mostly far less for a rep, who sees a part of it.

### Writes and rules

By default a write through the resource controller finds its row by the id alone:

* a PATCH that changes nothing (`{}`) answers with the row, whoever's it is, without asking a
  rule - and so does a POST with an `id` in its body, which finds that row instead of creating
  one;
* a relation given as an object (`buyer_workspace: {"id": 3}`) is linked, and saved, after the
  rules have been asked, so a row can be pointed at - and a related row changed through - one
  the caller may not read;
* a rule's no answers 200 with the unsaved row, the body's changes in it.

With `writesFollowRules` on - in `Config\RestExtension`, or per model by overriding
`writesFollowRules()`:

* PATCH, PUT and DELETE of a row the caller may not read is 404, as GET by id is: the row is
  `preRestGet()`'s to show (`isRestVisible()`);
* a POST creates: an `id` in its body is ignored;
* a relation given as an object is ignored, on every write. A relation is written by its column
  (`buyer_workspace_id`), which the model's rules see in `$item` - check there that the caller
  may read the row it points at;
* one row per request: a list is 400 `OneResourcePerRequest`;
* a rule's no is 403 `InsufficientAccess`.

A row the caller may read and change answers as before.

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
