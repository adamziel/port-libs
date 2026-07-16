# ATTACH Schema Cookie Reprepare Current Source Next100

## Behavior

This slice extends `SQLiteAttachSchemaCookieRepreparePlan` for prepared
statements whose schema dependencies are inside CTE bodies. The plan now filters
CTE aliases out of the schema-cookie dependency set, keeps the real main/temp/
attached table references, accepts bracket-quoted identifiers, normalizes
`sqlite_master` to `sqlite_schema`, and classifies `WITH ... INSERT` as a write
statement before reprepare retry.

The Application path is copied option import SQL that reads attached multisite
options through CTEs while another connection changes main/site schema cookies,
attaches a new blog database, or detaches an archive database. Active readers
finish on the current source and report `SQLITE_SCHEMA` on reset; inactive
readers retry; stale write statements block before retry.

## Evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteAttachSchemaCookieReprepareCurrentSourceNext100Test.php`
  - `1 test files, 65 assertions, 0 failures`
  - 59 focused PASS lines.
- Regression:
  `php tools/run-tests.php lanes/libsqlite/tests/SQLiteAttachSchemaCookieReprepareCurrentSourceNext84Test.php lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCookieCurrentSourceNext94Test.php lanes/libsqlite/tests/SQLiteAttachWalTempSchemaCacheCurrentSourceNext92Test.php`
  - `3 test files, 234 assertions, 0 failures`
- Application smoke:
  `php lanes/libsqlite/examples/application-attach-schema-cookie-reprepare-current-source-next100.php | php -r 'json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR); echo "json ok\n";'`
  - `json ok`

## Non-Overlap

This does not repeat accepted ATTACH temp/WAL trigger-view cache routing,
trigger-cache reprepare, JSON table source wiring, or batch97 trigger-view
current-source behavior. It is scoped to prepared statement schema-cookie
dependency extraction for CTE body sources and bracket/schema aliases in
`SQLiteAttachSchemaCookieRepreparePlan`.

## Dependency Closure

No new support component is needed. The slice reuses the existing bounded
native PHP attach/schema-cookie planner and focused PHP harness; no ext/sqlite
or external SQLite runner is required.
