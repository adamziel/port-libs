# ATTACH Schema-Cookie Reprepare Current Source Next84

This slice adds `SQLiteAttachSchemaCookieRepreparePlan`, a bounded native PHP
planner for prepared statement expiry across ATTACH, DETACH, schema writes, and
committed page-1 WAL schema-cookie changes.

The behavior is intentionally narrower than the accepted ATTACH WAL/temp
transaction routing and view-cache clusters. It models the prepared statement's
source schema and schema-cookie snapshot at prepare time, then reports whether
the statement can continue on its current source, returns `SQLITE_SCHEMA` on
reset, can be retried as a read, or must block before write retry.

Verification evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteAttachSchemaCookieReprepareCurrentSourceNext84Test.php`
- `php lanes/libsqlite/examples/application-attach-schema-cookie-reprepare-current-source.php`
- `php -l lanes/libsqlite/src/SQLiteAttachSchemaCookieRepreparePlan.php`
- `php -l lanes/libsqlite/tests/SQLiteAttachSchemaCookieReprepareCurrentSourceNext84Test.php`
- `php -l lanes/libsqlite/examples/application-attach-schema-cookie-reprepare-current-source.php`
- `git diff --check -- lanes/libsqlite`

Dependency closure: no new support component is needed. The slice reuses the
lane-local schema-cookie, ATTACH/DETACH, WAL page-1 cookie, and prepared SQL
table extraction primitives already present in libsqlite.

Non-overlap: avoids accepted ATTACH WAL/temp rollback routing, temp view/trigger
routing, prepared-statement lifecycle expiry, current/next schema cache SQL,
and batch80/81 ATTACH view-trigger routing by focusing on current-source
prepared statement invalidation after ATTACH/DETACH and schema-cookie changes.
