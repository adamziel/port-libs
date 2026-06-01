# real-upstream-corpus-upsert-returning-dynamic-20260601T004928Z-0

## Upstream source truth

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/returning1.test`
- Section `returning1-16.0`: `INSERT INTO t2 SELECT * FROM t1 RETURNING *`
- Section `returning1-16.1`: validates target table image after the transfer insert.

## Patch

- Added `SQLiteReturningTransferPlan`, a generic bounded model for
  `INSERT ... SELECT ... RETURNING *` transfer rows.
- Added `SQLiteRealUpstreamReturningTransferDynamicTest.php` with 1000 dynamic
  upstream-backed behavior cases plus source-truth, malformed-input, and
  dependency-closure guards.

## Non-overlap

This slice does not cover existing UPSERT conflict arms, returning result-column
names, DDL returning errors, virtual-table returning, QRF formatting, JSON table
sources, SELECT SQL text dispatch, grouped SELECT text, expression ORDER BY, or
VFS/B-tree/WAL storage clusters.

## Dependency closure

No new support component is required. The slice reuses the hydrated upstream
SQLite test checkout and lane-local PHP tests.
