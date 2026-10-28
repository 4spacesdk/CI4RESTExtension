# Changelog

## v1.0.23 (2026-10-28)

### Fixed bugs
* **The API parser's items declare their properties.** `ApiItem`, `EndpointItem`, `InterfaceItem`,
  `ParameterItem`, `PropertyItem` and `ApiParser` itself set properties they only named in a
  `@property` line, and PHP 8.2 and later reports each such property as deprecated each time it
  is set. One document of an app with some hundred endpoints was more than sixty thousand of
  them, and CodeIgniter logs every one with its stack trace: seconds for `generateOpenApi()` or
  an export, more memory than a request or a test has, and logs of a gigabyte a day. Every
  property is declared now, with the default it had, so nothing is reported and nothing else
  changes - the documents and exports are as they were. A test fails on any deprecation of the
  parsers.

### Upgrade guide
* Requires OrmExtension v1.1.10, whose model parser declares its properties the same way.
* An app that silenced the parsers' deprecations can stop doing so.

## v1.0.22 (2026-10-27)

### Enhancements
* **`ApiParser::generateOpenApi()` makes a whole, valid OpenAPI 3.0 document.** `generateSwagger()`
  gave the paths, and the app put them together with OrmExtension's schemas. Those named a
  relation as `type: "Workspace"` and a nullable as `type: "?int"`, which no OpenAPI reader takes,
  and left out a schema only another schema named. The new document has every endpoint, with an
  `operationId`, and the schemas of the models, interfaces and static schemas the endpoints name,
  and those they name in turn - a relation and an interface's property as a `$ref`, a `?int` as
  nullable, an `int[]` in the query as `ids=1,2,3`, a `File` as a field of a multipart body. Every
  `$ref` points at a schema. It is checked against an OpenAPI reader in the tests.
* **An endpoint's doc comment is its documentation.** The text above the tags is the operation's
  summary and description, as PHPDoc has them; `@summary` and the new `@description` say them over
  it. The text after ` - ` on a `@param` or `@parameter` is the parameter's description, and the
  comment after a `@property` the property's. A resource controller's overridden `get()`, `post()`
  and so on keep their text to themselves - it says why they are overridden - and are documented by
  `@summary` and `@description` only.

### Fixed bugs
* **`required=false` is not required.** It was the string `"false"`, which PHP takes for true, so
  `generateSwagger()` said such a parameter was required. A parameter without `required=` is still
  required, as it was.

### Upgrade guide
* Requires OrmExtension v1.1.9, which has the schemas of the models and the types of OpenAPI.
* Nothing else changes: `generateSwagger()`, apart from `required=false`, and the TypeScript, Vue and
  Xamarin exports are as they were. The text of a doc comment is not the summary in Api.ts.
* To move to the new document, build it with `generateOpenApi()` - see "API Parser" in the README -
  where you put together `generateSwagger()`'s paths and `ModelParser::generateSwagger()`'s schemas.
  Give it your `info`, `servers` and security schemes; `contact` belongs in `info`, and
  `definitions` is Swagger 2.0, not OpenAPI 3.

## v1.0.21 (2026-10-19)

### Fixed bugs
* **Two relations under the same parent in one request no longer lose all but the last.**
  `include=company.owner_team,company.country` is two includes of `company`, and with
  `relationsFollowRules` on each of them fetched the company again and wrote it over the row, so
  only the last one was there. Includes that ask nothing else of their parent are now made one
  before anything is fetched - one request to the related model instead of two, and nothing to
  write over. Without the rules the joins piled up on one query and both already arrived; that is
  unchanged. An include with a filter, an ordering or a limit of its own stays as it was: those
  belong to that one include, and folding two of them together would change what was asked.

### Security
* **A sub query followed by more is refused instead of answered wrongly.** A comma ends a sub
  query, so `include=a?include=b,c` is `a` with `b` under it and `c` as an include of its own -
  not, as it reads, `b` and `c` both under `a`. Where the model being asked has no `c` the
  request failed; where it happened to have one, the answer came back with `c` hanging off the
  wrong row and nothing to say so. No encoding got round it: the comma is decoded long before the
  parser sees it. It is now 422 `InvalidRequest`, naming the dotted form that says either meaning.

### Upgrade guide
* `include=a?include=b,c` and anything else that puts a comma after an `?include=` sub query now
  answers 422. Write `a.b,a.c` for both under `a`, or put the plain include first, as
  `c,a?include=b`. A sub query of `ordering` or `filter` followed by a comma is untouched.
* Nothing else to do. An `include` that was already right returns what it always did.

## v1.0.20 (2026-10-04)

### Security
* **Writes can follow the rules.** With `writesFollowRules` on, a write through the resource
  controller goes through the same rules as a read. Off, any caller may read any row: a PATCH
  that changes nothing, or a POST with an `id`, answers with the row that id names without
  asking a rule. And a relation given as an object is linked and saved after the rules have been
  asked. On:
  * PATCH, PUT and DELETE of a row `preRestGet()` hides is 404, as GET by id is;
  * a POST ignores an `id`, and every write ignores relations given as objects - a relation is
    written by its column, which the rules see;
  * a list of rows is 400 `OneResourcePerRequest`;
  * a rule's no is 403 `InsufficientAccess`, instead of 200 with the unsaved row.

  See "Writes and rules" in the README.
* `isRestVisible($id, $queryParser)` says whether the caller may read a row, by `preRestGet()`.

### Upgrade guide
* Nothing to do: with `writesFollowRules` off, nothing changes.
* Before turning it on, look for clients that create or change related rows through a relation
  given as an object, or post lists of rows: they write each row on its own, through its own
  resource, instead.

## v1.0.19 (2026-10-03)

### Enhancements
* **The API export reads more than the app's own controllers and interfaces.**
  `$apiControllerNamespace` and `$apiInterfaceNamespace` may be lists, and every namespace in them
  is exported - a composer package's along with the app's. Their directories come from
  CodeIgniter's autoloader, so subdirectories and packages work as `App\Controllers` always has. A
  controller by the same name in two namespaces is the first namespace's.
* `$resourceControllerClass` says which base classes make a controller a resource controller, a
  class or a list. It is `App\Core\ResourceController` unless it says otherwise, as it always was.

### Upgrade guide
* Requires CI4OrmExtension 1.1.7.
* Nothing else to do: with one namespace each, the export is the same, byte for byte.

## v1.0.18 (2026-10-02)

### Fixed bugs
* **With `relationsFollowRules` on, an include through a has-many further along its path is
  included at every level.** `notes?include=user.notes` and `orders?include=buyer_workspace.user`
  were left to RestExtension's own include, which includes nothing for the first relation and
  gives each row the has-many of a row of another table with the same id. The first relation now
  decides how the path is fetched, and the rest of it is the related model's include.

### Upgrade guide
* Nothing to do. With `relationsFollowRules` off, nothing changes; such a path is included as
  wrongly as before.

## v1.0.17 (2026-10-02)

### Enhancements
* **Relations can follow the related model's rules.** With `relationsFollowRules` on, a filter, an
  include or an ordering on a relation asks the related model's `preRestGet()` which rows the
  caller may read, instead of joining its table in past it, and a row they may not read counts as
  no row. Off by default; on, the answer is the same as before wherever nothing is hidden. See
  "Relations and rules" in the README.
* With it on, a has-many include is fetched for all rows in one request instead of one per row, and
  a has-one include with includes of its own in one request instead of one per row.
* `QueryParser::addFilter()` adds a filter that is already parsed.

### Upgrade guide
* Requires CI4OrmExtension 1.1.6, which says how a relation is joined (`RelationLink`).
* Nothing else to do: with `relationsFollowRules` off, nothing changes.
* Before turning it on, look for rules that live in `postRestGet()`: they still decide what an
  include holds, but not what a filter on the relation matches.

## v1.0.16 (2026-10-02)

### Enhancements
* **The TypeScript export can produce a complete Angular client.** With `typescriptAPIExportBaseClasses`
  on, `api:export` also writes `BaseApi.ts`, `ApiFilter.ts`, `ApiInclude.ts` and `ApiOrdering.ts`
  next to `Api.ts`, and `model:export` writes `BaseModel.ts` next to the models. Together with the
  generated files they compile under `strict`, `noImplicitOverride`, `exactOptionalPropertyTypes` and
  `noUncheckedIndexedAccess`. Until now every application had to write these itself.
* `typescriptObservableMethods` adds `find$()`, `count$()`, `save$()` and `delete$()` to the TypeScript
  endpoints. They return a typed Observable of model instances, for `rxResource`, `toSignal` and RxJS
  operators. `getClient()`, the only Observable so far, is typed `any`.
* `typescriptBaseApiImportPath` and `typescriptModelsImportPath` say where `Api.ts` imports `BaseApi`
  and the models from. They were fixed at `@app/core/http/Api/BaseApi` and `@app/core/models`.

### Fixed bugs
* **The TypeScript `Api.ts` imports the models that a request or response interface refers to.** It
  only imported those an endpoint names, so an interface with a model-typed property failed with
  `TS2552: Cannot find name`. The Vue export already did this.

### Upgrade guide
* Nothing to do. The new options default to off, and with them off the only change to the
  generated files is the added imports.



## v1.0.15 (2026-10-01)

### Fixed bugs
* **The Vue and TypeScript api clients build when an endpoint has a query parameter named `scope`,
  `summary`, `topic` or `method`. The endpoint class has properties with those names, and gave the
  parameter a method of the same name, which TypeScript refuses with `TS2300: Duplicate identifier`.
  A query parameter named `scope` or `summary` now takes the place of the property, which BaseApi
  never reads. One named `topic` or `method` gets the method `topicParameter()` or `methodParameter()`,
  since BaseApi reads `method` to choose between post, put and patch.**

### Upgrade guide
* Nothing to do. Every endpoint without such a parameter is generated as before.



## v1.0.14 (2026-09-22)

### Security
* The access, error and blocked logs no longer store the access token. `user_id` and `client_id`
  still say who called.
* The error log writes the value of every header that carries a credential as `[redacted]`:
  `Authorization`, `Proxy-Authorization`, `Cookie` and `Set-Cookie`, plus any the application names in
  `$redactedHeaders`. It used to store them all as they were, for every request that ended in an
  exception, refused ones included.
* A filter, a search, `fields` and `ordering` may only name a column of the model's table, or of a
  related model's, and never one of the entity's `hiddenFields`. Filters and `fields` used to reach
  the query builder as they were written, so a hidden column such as a password hash could be read a
  character at a time. A hidden column is refused with the same words as one that is not there.

### Enhancements
* `RestExtension\Logs::PruneOlderThan($days)` removes old rows from the three log tables, in
  portions. Nothing removed them before; schedule it from the application.

### Fixed bugs
* The hooks and the log cleanup connect to the default database group when `databaseGroupName` is not
  set, instead of the group named `default`. Under test the default is the test database.
* Deprecations from PHP 8.2 to 8.5: dynamic properties on `QueryFilter`, `QueryInclude` and `QueryOrder`,
  implicitly nullable parameters in `Core\Model`, a `(double)` cast, and a null message passed to
  `UnauthorizedException`.

### Dependencies
* `codeigniter4/framework` and `4spacesdk/ci4ormextension` are required, as the code has always
  needed them. The suggestion of `4spacesdk/ci4restextension-zmq` is gone.

### Upgrade guide
* A filter, `fields` or `ordering` on a column that is not there, or is hidden, is now an
  `InvalidRequestException` (400) instead of a server error or an answer.
* Existing rows keep the tokens and headers they were written with. Clear `access_token` and redact
  `api_error_logs.headers` with a migration of your own if they matter.



## v1.0.13 (2026-09-20)

### Fixed bugs
* An ordering that names a field, relation or direction that is not there is refused, instead of a
  server error or a list sorted the other way.



## v1.0.12 (2026-09-20)

### Fixed bugs
* A by-id read that finds nothing answers 404 `ResourceNotFound`, instead of a resource with every
  field null.



## v1.0.11 (2025-02-04)

### Enhancements
* The resource api path can be overridden on resource controllers.
