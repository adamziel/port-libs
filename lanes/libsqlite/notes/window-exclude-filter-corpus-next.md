# Window EXCLUDE/FILTER Corpus Next

2026-05-27 isolated slice `yield-sqlite-window-exclude-filter-corpus-next`.

## Behavior

Adds bounded native PHP window aggregate frame coverage in `SQLiteWindowFunction::aggregateRows()` for upstream SQLite window behavior:

- ROWS-style frame bounds with start/end clamping.
- `EXCLUDE NO OTHERS`, `EXCLUDE CURRENT ROW`, `EXCLUDE GROUP`, and `EXCLUDE TIES`.
- Aggregate `FILTER` row selection after frame exclusion using SQLite-style scalar truthiness.
- Window `count`, numeric `sum`, `group_concat`-style text diagnostics, and frame-index diagnostics.
- NULL, boolean, BLOB peer-key, malformed input, and invalid option guards.

This does not claim a new mapped upstream inventory unit; it adds focused PHP PASS cases over the already mapped window corpus surface.

## Verification

```text
php -l lanes/libsqlite/src/SQLiteWindowFunction.php
No syntax errors detected in lanes/libsqlite/src/SQLiteWindowFunction.php

php -l lanes/libsqlite/tests/SQLiteWindowExcludeFilterCorpusTest.php
No syntax errors detected in lanes/libsqlite/tests/SQLiteWindowExcludeFilterCorpusTest.php

php -l lanes/libsqlite/examples/application-window-exclude-filter-summary.php
No syntax errors detected in lanes/libsqlite/examples/application-window-exclude-filter-summary.php

php tools/run-tests.php lanes/libsqlite/tests/SQLiteWindowExcludeFilterCorpusTest.php
Focused test run: 1 selected test files (root lock skipped)
37 PASS lines
1 test files, 38 assertions, 0 failures

php lanes/libsqlite/examples/application-window-exclude-filter-summary.php
passed and emitted copied wp_options window EXCLUDE/FILTER JSON summary
```

## Status Delta

- `phpPass`: `1336 -> 1373` (`+37` verified PASS lines).
- `benchmarkDenominator.mapped`: unchanged; no new hydrated upstream inventory unit claimed.
- Root harness: not run; isolated micro-slice.

## Non-Overlap

Avoids accepted window ranking/value helpers, JSON aggregate/window helpers, JSON table window-ranking, SELECT GROUP BY/HAVING text, SQL expression `ORDER BY`, VFS writer/sync/lock clusters, WAL savepoint byte truncation, rollback-journal commit/apply, B-tree page move/root-collapse/overflow release, and the batch3 scalar/JSON/SELECT/WAL/schema corpus.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PHP libsqlite scalar/BLOB value helpers and TestRunner; it does not require ext/sqlite, shelling out to SQLite, or shared dependency activation.
