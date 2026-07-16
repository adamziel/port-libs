# SQLite ATTACH Temp/Main WAL View Cache Current/Next 78

## Scope

- Added `SQLiteAttachWalTempViewCachePlan::viewDependencyCachePlan()` for prepared view-cache invalidation across temp, main, and attached schemas.
- Covers the SQLite edge where a prepared view row stays stable while the table(s) referenced by the view SQL move to a new rootpage or schema winner after current/next schema-record updates.
- Tracks WAL page-one schema-cookie sources separately from dependency reprepare decisions so Application import diagnostics can explain why a prepared view is stale without duplicating WAL writer/checkpoint behavior.
- Added `application-attach-temp-main-wal-view-cache-current-next78.php` for copied `wp_options` temp/main/site view diagnostics.

## Focused Evidence

Command:

```bash
php tools/run-tests.php lanes/libsqlite/tests/SQLiteAttachTempMainWalViewCacheCurrentNext78Test.php
```

Result:

```text
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 56 assertions, 0 failures
```

Example smoke:

```bash
php lanes/libsqlite/examples/application-attach-temp-main-wal-view-cache-current-next78.php
```

Result summary:

```text
"operation": "attach-temp-main-wal-view-cache-current-next78"
"requires_reprepare": true
"reprepare_views": ["autoloaded_options", "site.site_options"]
```

Dashboard delta:

- `phpPass`: `29382 -> 29438` (`+56` focused PASS lines verified locally).
- `benchmarkDenominator.mapped`: unchanged at `464 / 1589`.

## Non-Overlap

This slice does not repeat accepted ATTACH WAL/temp rollback routing, temp schema lifecycle, schema-trigger cache invalidation, VFS file writer/lock/sync application, WAL savepoint/checkpoint byte application, B-tree freeblock/freelist work, JSON hidden/visible constraints, or SELECT SQL text execution. The new behavior is specifically view dependency cache invalidation when a stable view definition points at a changed temp/main/attached table winner.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PHP attached schema catalog and WAL schema-cookie diagnostics.
