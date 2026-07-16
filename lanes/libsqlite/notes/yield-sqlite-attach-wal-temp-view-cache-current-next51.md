# SQLite ATTACH WAL Temp View Cache Current/Next 51

## Scope

- Added `SQLiteAttachWalTempViewCachePlan` to compose existing ATTACH temp view-trigger yield routing, WAL checkpoint/append current-next planning, and schema-cache resolution snapshots.
- Covers main, temp, and attached-schema Application `wp_options` view-trigger writes where prepared table/index winners remain current after ordinary WAL DML, but require reprepare after schema-record changes alter rootpage or schema resolution.
- Added `application-attach-wal-temp-view-cache-current-next51.php` as the Application smoke for active plugin imports over copied `wp_options` metadata.

## Focused Evidence

Command:

```bash
php tools/run-tests.php lanes/libsqlite/tests/SQLiteAttachWalTempViewCacheCurrentNext51Test.php
```

Result:

```text
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 69 assertions, 0 failures
```

Dashboard delta:

- `phpPass`: `18565 -> 18634` (`+69` focused PASS lines verified locally).
- `benchmarkDenominator.mapped`: unchanged; no new upstream inventory unit was mapped.

## Non-Overlap

This slice does not repeat accepted ATTACH temp WAL view-trigger routing, generic attach schema-cache invalidation, WAL byte truncation, VFS writer/sync/lock application, JSON table source/cursor/constraint work, B-tree page moves/freelist release, or SELECT SQL text/subquery/group/order behavior. The new behavior is the combined current/next cache-reprepare decision when ATTACH/TEMP trigger writes and WAL plans are evaluated against cached table/index winners.

## Dependency Closure

No new support component is needed. The slice reuses existing native PHP primitives: `SQLiteAttachedSchemaCatalog`, `SQLiteAttachTempWalViewTriggerPlan`, and `SQLiteWalAppendPlan`.
