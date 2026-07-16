# Window GROUPS/RANGE Current Next18

2026-05-27 isolated slice `yield-sqlite-window-groups-range-current-next18`.

## Behavior

- Adds bounded parser/executor wiring for aggregate window SQL text:
  `count`, `sum`, and `group_concat` with `OVER (...)`.
- Supports `ROWS`, `RANGE`, and `GROUPS` frames of the form
  `BETWEEN CURRENT ROW AND N FOLLOWING`, including `EXCLUDE CURRENT ROW`,
  `EXCLUDE GROUP`, and `EXCLUDE TIES`.
- Keeps frame execution after `WHERE` filtering and before final
  `ORDER BY`/`LIMIT`/`OFFSET`, matching SQLite SELECT pipeline ordering for
  the bounded native row-array executor.

## Evidence

Focused new test:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteWindowGroupsRangeCurrentNext18Test.php
Focused test run: 1 selected test files (root lock skipped)
45 PASS lines, 45 assertions, 0 failures
```

Adjacent window regression check:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteWindowPeerRangeCurrentNext16Test.php lanes/libsqlite/tests/SQLiteWindowFrameBoundaryCorpusTest.php lanes/libsqlite/tests/SQLiteWindowGroupsRangeCurrentNext18Test.php
3 test files, 161 assertions, 0 failures
```

Application smoke:

```text
php lanes/libsqlite/examples/application-window-groups-range-current-next.php
passed and emitted copied wp_options GROUPS/RANGE current-to-following window summaries
```

## Status

- `phpPass`: `6121 -> 6166` from the 45 newly verified PASS lines.
- `benchmarkDenominator.mapped`: unchanged; this is focused PHP pass growth
  over the already mapped window-function surface.
- Root harness: not run for this isolated micro-slice.

## Non-Overlap

This slice avoids accepted helper-level window peer/range frame coverage,
window EXCLUDE/FILTER helper corpus, JSON aggregate/window helpers, JSON table
window ranking, parser-level SELECT/JOIN/GROUP BY/subquery/ORDER-expression
clusters, VFS/WAL/B-tree storage clusters, Unicode GLOB, and the accepted
batch16 window peer/range helper corpus. The new behavior is parser-level
aggregate window frame execution for `GROUPS`/`RANGE CURRENT ROW ... FOLLOWING`.

## Dependency Closure

No new support component is needed. The patch reuses the existing native
SELECT parser/executor, expression evaluator, and window frame helper code.
