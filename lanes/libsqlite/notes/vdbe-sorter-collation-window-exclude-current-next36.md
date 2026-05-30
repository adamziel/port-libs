# VDBE Sorter Collation Window EXCLUDE CURRENT Next36

2026-05-27 isolated slice `yield-sqlite-vdbe-sorter-collation-window-exclude-current-next36`.

## Behavior

- Added bounded `EXCLUDE CURRENT ROW` support to `SQLiteVdbeWindowAggregateCursor`.
- The exclusion is applied after sorter/partition frame selection and before aggregate stepping, so FILTER, count/sum/total/avg/min/max/group_concat, summaries, and drain loops see the same frame rows SQLite would feed to aggregate step callbacks.
- Empty excluded frames are valid and report null summary bounds with zero frame rows.
- Focused coverage includes ROWS frames, numeric RANGE frames, descending order, NOCASE sorter peers, partition boundaries, SQL filter truthiness, NULL aggregate input handling, and unsupported EXCLUDE-mode guards.

## Application Smoke

`lanes/libsqlite/examples/application-vdbe-sorter-window-exclude-current-next36.php` reports copied `wp_options` rows ordered with NOCASE sorter collation and partitioned by site/autoload, then aggregated over current/next frames where the current row is excluded before FILTER and aggregate stepping. This is useful for local import diagnostics that need neighbor summaries without counting the option row currently being reviewed.

## Verification

```text
php -l lanes/libsqlite/src/SQLiteVdbeWindowAggregateCursor.php
No syntax errors detected in lanes/libsqlite/src/SQLiteVdbeWindowAggregateCursor.php

php -l lanes/libsqlite/tests/SQLiteVdbeSorterCollationWindowExcludeCurrentNext36Test.php
No syntax errors detected in lanes/libsqlite/tests/SQLiteVdbeSorterCollationWindowExcludeCurrentNext36Test.php

php -l lanes/libsqlite/examples/application-vdbe-sorter-window-exclude-current-next36.php
No syntax errors detected in lanes/libsqlite/examples/application-vdbe-sorter-window-exclude-current-next36.php

php tools/run-tests.php lanes/libsqlite/tests/SQLiteVdbeSorterCollationWindowExcludeCurrentNext36Test.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 68 assertions, 0 failures
```

Focused PASS-line delta: +60 PHP PASS lines. `lane-status.json` `phpPass` moves from 12903 to 12963 for this isolated worktree.

## Non-Overlap

This slice avoids accepted parser-level SELECT SQL text/JOIN/GROUP BY/subquery/expression ORDER BY work, JSON table cursor/source/constraint work, VFS/WAL writer/checkpoint/savepoint/rollback clusters, B-tree page move/root-collapse/overflow/freelist clusters, Unicode GLOB, VDBE aggregate DISTINCT/ORDER BY cursors, sorter DISTINCT group cursors, and accepted VDBE window peer/RANGE behavior. The new behavior is specifically VDBE-style sorter collation window aggregate frame exclusion for `EXCLUDE CURRENT ROW`.

## Dependency Closure

No new support component is needed. The slice reuses existing native PHP VDBE sort comparison, affinity/collation comparison, numeric aggregate, and text aggregate helpers.
