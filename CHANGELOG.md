# Changelog

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
