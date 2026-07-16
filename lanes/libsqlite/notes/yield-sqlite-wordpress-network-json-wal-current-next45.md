# Application Network JSON WAL Current Next45

Status: focused PHP corpus growth for copied Application multisite JSON imports with WAL current/next frame accounting.

## Implementation

- Added `SQLiteNetworkJsonWalCurrentNextPlan`.
- Covers blog-scoped `wp_options`, subsite `wp_{blog_id}_options`, and network-scoped `wp_sitemeta` JSON import batches.
- Tracks JSON text, JSON subtype, and JSONB payloads, path extraction, malformed payload rollback, current-reader frame pinning, next-reader frame visibility, table identity, conflict keys, dirty pages, and WAL frame ordering.
- Added `application-network-json-wal-current-next45.php` as a Application multisite smoke without `ext/sqlite`.

## Verification

Focused tests:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteNetworkJsonWalCurrentNext45Test.php
Focused test run: 1 selected test files (root lock skipped)
56 PASS lines
1 test files, 142 assertions, 0 failures
```

Application smoke:

```text
php lanes/libsqlite/examples/application-network-json-wal-current-next45.php
```

## Dashboard Delta

- `lane-status.json` `phpPass`: `16685` -> `16741` (`+56` verified focused PASS lines).
- `benchmarkDenominator.mapped`: unchanged; this is a focused PHP behavior slice, not a newly mapped upstream inventory unit.

## Non-Overlap

Avoids accepted JSON table cursor/source/hidden/visible constraint clusters, WAL savepoint byte truncation, VFS savepoint rollback, rollback-journal commit/apply, SELECT SQL subquery/GROUP/ORDER text dispatch, Unicode GLOB, and B-tree overflow/page-move/root-collapse clusters. The new surface is multisite Application network JSON import routing with current/next WAL frame visibility across `wp_options` and `wp_sitemeta`.

## Dependency Closure

No new support component is needed. The slice reuses existing native PHP JSON validity/extract/JSONB helpers and models WAL current/next frame accounting lane-locally.

## Next

Wire this network import planner to broader pager/VFS transaction application once a native table/index writer owns multisite row page images.
