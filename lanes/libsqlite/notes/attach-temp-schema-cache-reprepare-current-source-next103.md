# ATTACH Temp Schema Cache Reprepare Current Source Next103

## Behavior

This slice extends `SQLiteAttachSchemaCookieRepreparePlan` with
`currentSourceNext103()`, which reports shared schema-cache reload decisions at
the current-source/next-source boundary. It covers prepared statements that
finish on the current source while ATTACH, temp schema DDL, WAL page-1 schema
cookies, and shared-cache entries move underneath the connection.

The Application path is a copied multisite import that keeps prepared statements
for `main`, attached `site`, temp staging tables, and a newly attached blog
database. Stale shared-cache entries for `main`, `site`, and `blog103` are
classified for reload before next-source reprepare; temp schema changes remain
uncached but still expire temp readers; active readers finish now and reload on
reset; writes block before retry.

## Evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteAttachTempSchemaCacheReprepareCurrentSourceNext103Test.php`
  - `1 test files, 74 assertions, 0 failures`
  - 70 focused PASS lines.
- Application smoke:
  `php lanes/libsqlite/examples/application-attach-temp-schema-cache-reprepare-current-source-next103.php | php -r 'json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR); echo "json ok\n";'`
  - `json ok`

## Non-Overlap

This avoids accepted ATTACH WAL/temp rollback routing, trigger/view cache
reprepare, next92/next100 statement dependency extraction, JSON table source
work, VFS writer/sync/lock/rollback clusters, WAL checkpoint/savepoint
byte-truncation clusters, and B-tree page/freelist clusters. The new surface is
the shared schema-cache reload/reuse decision for ATTACH/temp schema-cache
reprepare after schema cookies change.

## Dependency Closure

No new support component is needed. The slice reuses the lane-local ATTACH
schema-cookie planner, WAL page-1 cookie extraction model, and focused PHP test
harness; it does not require ext/sqlite or the upstream Tcl runner.
