# Changelog

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
