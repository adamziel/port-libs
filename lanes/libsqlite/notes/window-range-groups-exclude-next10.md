# Window RANGE/GROUPS EXCLUDE Next10

2026-05-27 isolated slice `yield-sqlite-window-range-groups-exclude-next10`.

## Behavior

Adds bounded native PHP window frame-unit coverage in
`SQLiteWindowFunction::aggregateFrameRows()`:

- Existing `aggregateRows()` remains the ROWS-frame compatibility entry point.
- `RANGE` frames use numeric ORDER BY distance bounds and include peer rows at
  `CURRENT ROW`.
- `GROUPS` frames advance by peer groups rather than physical rows.
- `EXCLUDE CURRENT ROW`, `EXCLUDE GROUP`, and `EXCLUDE TIES` apply after RANGE
  or GROUPS frame selection.
- FILTER truthiness applies after frame selection and exclusion.
- Diagnostics cover `count`, numeric `sum`, `group_concat`, frame indexes,
  numeric boolean keys, decimal RANGE keys, NULL peer keys, BLOB peer keys, and
  malformed input guards.

No new upstream inventory unit is claimed; this adds focused PHP PASS cases over
the already mapped SQLite window-function corpus surface.

## Verification

```text
php -l lanes/libsqlite/src/SQLiteWindowFunction.php
No syntax errors detected in lanes/libsqlite/src/SQLiteWindowFunction.php

php -l lanes/libsqlite/tests/SQLiteWindowRangeGroupsExcludeNext10Test.php
No syntax errors detected in lanes/libsqlite/tests/SQLiteWindowRangeGroupsExcludeNext10Test.php

php -l lanes/libsqlite/examples/application-window-range-groups-exclude.php
No syntax errors detected in lanes/libsqlite/examples/application-window-range-groups-exclude.php

php tools/run-tests.php lanes/libsqlite/tests/SQLiteWindowRangeGroupsExcludeNext10Test.php
Focused test run: 1 selected test files (root lock skipped)
58 PASS lines
1 test files, 58 assertions, 0 failures

php lanes/libsqlite/examples/application-window-range-groups-exclude.php --self-test
application window RANGE/GROUPS EXCLUDE smoke passed
```

## Status Delta

- `phpPass`: `3236 -> 3294` (`+58` verified focused PASS lines).
- `benchmarkDenominator.mapped`: unchanged; no fresh upstream inventory row.
- Root harness: not run; isolated micro-slice.

## Non-Overlap

Avoids accepted ROWS-style window frame boundary coverage, window EXCLUDE/FILTER
row-offset corpus, grouped SELECT SQL text, SQL expression `ORDER BY`, SELECT
subqueries, JSON table source/cursor/constraint work, Unicode GLOB, VFS
writer/sync/lock clusters, WAL byte truncation/checkpoint/rollback-journal
application, B-tree page move/root-collapse/overflow release, and batch6/7
partial high-yield corpus surfaces.

## Dependency Closure

No new support component is needed. The slice reuses existing native PHP
libsqlite window/BLOB helpers and TestRunner; it does not require ext/sqlite,
SQLite shell or Tcl runners, or shared dependency activation.
