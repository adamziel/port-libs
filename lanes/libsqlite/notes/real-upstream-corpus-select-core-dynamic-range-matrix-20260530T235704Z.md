# Real Upstream SELECT Core Dynamic Range Matrix

- Session: `port-dev-sqlite-yield-dyn-real-select-20260530T235704Z`
- Micro-slice: `real-upstream-corpus-select-core-dynamic-20260530T235704Z-0`
- Base accepted HEAD: `d045774aa6bf87ca954fff751277766f57e01075`
- New focused test: `lanes/libsqlite/tests/SQLiteRealUpstreamSelectCoreDynamicRangeMatrixTest.php`

## Upstream Source Truth

Hydrated upstream files used from `/home/claude/port-libs/.upstream-cache/libsqlite/test`:

- `select2.test`: `select2-2.2` large-table range/count scans and ordered result slices.
- `select3.test`: `select3-2` grouped aggregate rows with `HAVING`.
- `select5.test`: `select5-2.3` grouped `HAVING` behavior.
- `select6.test`: `select6-1.2` derived-table aggregate behavior.

## Coverage Added

The slice adds `1001` distinct focused TestRunner PASS cases:

- 1 upstream citation/provenance case.
- 250 dynamic `select2.test` range-count cases over `f1` windows.
- 250 dynamic `select2.test` ordered bucket `LIMIT/OFFSET` slices.
- 250 dynamic `select3.test` grouped `HAVING` span cases.
- 250 dynamic `select5.test` plus `select6.test` derived grouped `HAVING` cases.

Focused verification result:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamSelectCoreDynamicRangeMatrixTest.php
1 test files, 4008 assertions, 0 failures
```

Expected selected throughput movement: `1238327 -> 1239328` PASS lines (`+1001`). Mapped denominator remains `1589 / 1589`.

## Non-Overlap

This is intentionally separate from the accepted `SQLiteRealUpstreamSelectCoreDynamicBatch0Test.php` threshold loops. It uses distinct range-window counts, ordered bucket slices, grouped `HAVING` span matrices, and derived grouped `HAVING` matrices rather than repeating the accepted direct equality, simple threshold, or base grouped-count loops.

## Blocker Found

The first red run attempted ungrouped `SELECT count(*), sum(f2), min(f3), max(f3) ...` from the same `select2-2.2` range source. The current executor rejected all 250 cases with:

```text
SQLite SELECT SQL GROUP BY supports one aggregate value column
```

The ready batch keeps the supported count aggregate shape. A follow-up behavior fix can unlock a larger upstream SELECT aggregate batch by adding multi-aggregate projection support for ungrouped aggregate SELECT statements.

## Dependency Closure

No new support component is needed. This reuses the existing `SQLiteSelectSql` row-array executor, local TestRunner harness, and the hydrated upstream SQLite test checkout as source truth.
