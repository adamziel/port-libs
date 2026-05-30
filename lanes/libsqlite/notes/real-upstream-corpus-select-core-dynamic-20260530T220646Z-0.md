# real-upstream-corpus-select-core-dynamic-20260530T220646Z-0

Added `SQLiteRealUpstreamSelectHViewOmitUnusedDynamicTest.php`.

## Upstream source truth

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/selectH.test`

## Ported scenarios

- `selectH-3.1`: `SELECT count(*) FROM v1 WHERE c60=60` over a four-arm
  `UNION ALL` view shape.
- `selectH-3.3`: `SELECT count(a) FROM v1 WHERE c60=60` over the same
  view/subquery shape.
- `selectH-3.4`: `SELECT a FROM v1 WHERE c60=60` materializes all four arm
  projection values.
- `selectH-3.6`: `SELECT x FROM v1 WHERE c60=60` materializes the otherwise
  unused side-effect-column position only when selected.

## Yield

- Added `1001` focused TestRunner PASS cases.
- Added `15005` focused behavior assertions in the new selected test file.
- Mapped denominator remains unchanged because `selectH.test` is already
  represented in the hydrated upstream inventory.

## Non-overlap

This slice owns the residual `selectH.test` four-arm view/subquery
omit-unused-column cluster. It does not repeat the accepted `selectH-1.2`,
`selectH-2.1`, or `selectH-5` batches, the accepted `select1` through
`selectG` core batches, grouped SELECT text, expression `ORDER BY`, JSON table
source/cursor/constraint work, WAL/VFS/B-tree surfaces, or metadata-only
runner rows.

## Dependency closure

No new support component is needed. The batch reuses the existing
`SQLiteSelectSql` parser/executor and hydrated upstream SQLite source cache.

## Verification

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamSelectHViewOmitUnusedDynamicTest.php`
  - `1 test files, 15005 assertions, 0 failures`
