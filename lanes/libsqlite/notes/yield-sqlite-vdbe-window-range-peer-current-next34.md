# VDBE Window RANGE Peer Current Next34

2026-05-27 isolated slice `yield-sqlite-vdbe-window-range-peer-current-next34`.

## Behavior

- Extends `SQLiteVdbeWindowAggregateCursor` beyond positional `ROWS` frames to
  support bounded `RANGE` and `GROUPS` frames.
- Covers SQLite-style `RANGE BETWEEN CURRENT ROW AND N FOLLOWING` peer
  expansion over numeric ORDER BY keys, with partition clipping and FILTER
  truthiness applied after frame selection.
- Preserves existing `ROWS` constructor behavior and keeps `ROWS`/`GROUPS`
  offsets integer-only while allowing fractional numeric `RANGE` offsets.

## Verification

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteVdbeWindowRangePeerCurrentNext34Test.php lanes/libsqlite/tests/SQLiteVdbeWindowAggregateCurrentNext25Test.php
Focused test run: 2 selected test files (root lock skipped)
2 test files, 110 assertions, 0 failures
```

New focused PASS-line delta: `+50` from
`SQLiteVdbeWindowRangePeerCurrentNext34Test.php`.

## Application Smoke

```text
php lanes/libsqlite/examples/application-vdbe-window-range-peer-current-next34.php --self-test
application VDBE window RANGE peer current/following smoke passed
```

The smoke models copied `wp_options` rows scored by peer buckets, showing
autoloaded option byte summaries over current peer groups plus following RANGE
bands without requiring ext/sqlite.

## Status Delta

- `phpPass`: `11752 -> 11802` from the 50 newly verified focused PASS lines in
  this clean worktree.
- `benchmarkDenominator.mapped`: unchanged; no fresh upstream inventory unit is
  claimed.
- Root harness: not run; isolated micro-slice.

## Non-Overlap

Avoids accepted helper-level window peer/RANGE coverage, parser-level window
GROUPS/RANGE SQL text, VDBE aggregate ORDER BY/DISTINCT cursors, JSON table
window ranking, SELECT SQL text/JOIN/GROUP BY/subquery/ORDER clusters, VFS/WAL
transaction application, B-tree page-move/root-collapse/overflow release, and
Unicode GLOB clusters. This slice is specifically the VDBE aggregate cursor
frame-boundary primitive for numeric RANGE peer frames.

## Dependency Closure

No new support component is needed. The patch reuses existing native PHP
sort-compare, numeric aggregate, text aggregate, and TestRunner components; it
does not require ext/sqlite, SQLite shell/Tcl runners, or shared dependency
activation.
