# Window Peer RANGE Current Next16

2026-05-27 isolated slice `yield-sqlite-window-peer-range-current-next16`.

## Behavior

Adds bounded native PHP support and focused coverage for SQLite window
`RANGE` frames whose numeric bounds are real values rather than integers:

- `RANGE BETWEEN CURRENT ROW AND <fraction> FOLLOWING` includes the current
  peer group plus numeric-distance following rows.
- Fractional `PRECEDING` and combined preceding/following bands include exact
  decimal boundary rows despite PHP binary-float rounding.
- `EXCLUDE CURRENT ROW`, `EXCLUDE GROUP`, `EXCLUDE TIES`, and FILTER-style
  truthiness apply after fractional RANGE frame selection.
- `ROWS` and `GROUPS` retain integer offset requirements, while integer-valued
  floats remain accepted for compatibility with existing callers.

No new upstream inventory unit is claimed; this adds focused PHP PASS cases over
the already mapped SQLite window-function corpus surface.

## Verification

```text
php -l lanes/libsqlite/src/SQLiteWindowFunction.php
No syntax errors detected in lanes/libsqlite/src/SQLiteWindowFunction.php

php -l lanes/libsqlite/tests/SQLiteWindowPeerRangeCurrentNext16Test.php
No syntax errors detected in lanes/libsqlite/tests/SQLiteWindowPeerRangeCurrentNext16Test.php

php -l lanes/libsqlite/examples/application-window-peer-range-current-next.php
No syntax errors detected in lanes/libsqlite/examples/application-window-peer-range-current-next.php

php tools/run-tests.php lanes/libsqlite/tests/SQLiteWindowPeerRangeCurrentNext16Test.php
Focused test run: 1 selected test files (root lock skipped)
58 PASS lines
1 test files, 58 assertions, 0 failures

php lanes/libsqlite/examples/application-window-peer-range-current-next.php --self-test
application window peer RANGE current/following smoke passed
```

## Status Delta

- `phpPass`: `5433 -> 5491` (`+58` verified focused PASS lines).
- `benchmarkDenominator.mapped`: unchanged; no fresh upstream inventory row.
- Root harness: not run; isolated micro-slice.

## Non-Overlap

Avoids accepted ROWS-style boundary coverage, prior RANGE/GROUPS integer-offset
EXCLUDE coverage, JSON table windows/cursor/source/constraints, SELECT SQL text
GROUP BY/subquery/ORDER behavior, Unicode GLOB, VFS writer/sync/lock/rollback
clusters, WAL byte truncation/checkpoint/rollback-journal application, and
B-tree page move/root-collapse/overflow release clusters.

## Dependency Closure

No new support component is needed. The slice reuses existing native PHP
libsqlite window helpers and TestRunner; it does not require ext/sqlite, SQLite
shell/Tcl runners, or shared dependency activation.
