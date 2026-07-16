# Window Frame Boundary Corpus Next6

2026-05-27 isolated slice `yield-sqlite-window-frame-boundary-corpus-next6`.

## Behavior

Adds a focused native PHP corpus for upstream SQLite ROWS-style window frame
boundary behavior in `SQLiteWindowFunction::aggregateRows()`:

- `CURRENT ROW`, `N PRECEDING`, `N FOLLOWING`, and wide clamped frame bounds.
- Boundary clamping at the first and last partition rows.
- `EXCLUDE CURRENT ROW`, `EXCLUDE GROUP`, and `EXCLUDE TIES` at frame edges.
- `FILTER` application after frame boundary and exclusion selection.
- `count`, numeric `sum`, `group_concat` diagnostics, and frame index evidence.
- NULL value aggregation, boolean numeric sums, BLOB peer keys, string numeric
  filter truthiness, and malformed input guards.

The slice does not claim a new mapped upstream inventory unit; it adds focused
PHP PASS cases over the already mapped window-function corpus surface.

## Verification

```text
php -l lanes/libsqlite/tests/SQLiteWindowFrameBoundaryCorpusTest.php
No syntax errors detected in lanes/libsqlite/tests/SQLiteWindowFrameBoundaryCorpusTest.php

php -l lanes/libsqlite/examples/application-window-frame-boundary-summary.php
No syntax errors detected in lanes/libsqlite/examples/application-window-frame-boundary-summary.php

php tools/run-tests.php lanes/libsqlite/tests/SQLiteWindowFrameBoundaryCorpusTest.php
Focused test run: 1 selected test files (root lock skipped)
58 PASS lines
1 test files, 58 assertions, 0 failures

php lanes/libsqlite/examples/application-window-frame-boundary-summary.php
passed and emitted copied wp_options window frame boundary JSON summary
```

## Status Delta

- `phpPass`: `2017 -> 2075` (`+58` verified PASS lines).
- `benchmarkDenominator.mapped`: unchanged; no fresh upstream inventory row.
- Root harness: not run; isolated micro-slice.

## Non-Overlap

Avoids accepted window ranking/value helpers, JSON table window ranking, window
EXCLUDE/FILTER corpus row set, SQL expression `ORDER BY`, grouped SELECT SQL
text, SELECT subqueries, JSON table source/cursor/constraint work, VFS
writer/sync/lock clusters, WAL savepoint byte truncation, rollback-journal
commit/apply, B-tree page move/root-collapse/overflow release, and batch5a
view/trigger/index/temp/collation/JSON/PRAGMA/trigger/VALUES corpus work.

## Dependency Closure

No new support component is needed. The slice reuses existing native PHP
libsqlite window/BLOB helpers and TestRunner; it does not require ext/sqlite,
shelling out to SQLite, or shared dependency activation.
