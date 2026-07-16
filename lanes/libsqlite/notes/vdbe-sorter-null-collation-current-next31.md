# VDBE Sorter NULL Collation Current/Next 31

## Behavior

- Added `SQLiteVdbeSorterYieldCursor` for the VDBE sorter loop shape where sorted rows are consumed through current/next or data/next calls.
- The cursor reuses `SQLiteVdbeSortCompare::sortedRowTrace()` so each yielded row carries the sorted record, original input sequence, previous sequence, raw comparator result, stable-tie marker, and deciding comparison step.
- Focused coverage exercises mixed `NOCASE`, `RTRIM`, numeric affinity, explicit `NULLS FIRST` / `NULLS LAST`, descending priority order, stable duplicate records, EOF behavior, and invalid input guards.
- The Application smoke uses copied `wp_options`-style autoload and option-name rows to show sorter diagnostics without requiring `ext/sqlite`.

## Evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteVdbeSorterNullCollationCurrentNext31Test.php`
  - `1 test files, 103 assertions, 0 failures`
  - 87 new focused PASS lines.
- `php lanes/libsqlite/examples/application-vdbe-sorter-null-collation-current-next.php`
  - Emits sorted rowids and per-row current/next comparison summaries.

## Status Delta

- `lane-status.json` `phpPass`: `10687 -> 10774`.
- `phpFail`: `0`.
- `benchmarkDenominator.mapped`: unchanged; this is focused native PHP behavior coverage, not a fresh upstream inventory unit.

## Non-Overlap

This slice does not repeat accepted SQL expression `ORDER BY`, parser-level SELECT SQL text dispatch, aggregate ORDER BY cursors, VDBE DISTINCT group cursors, Unicode GLOB ranges, JSON table source/cursor work, WAL/VFS writer application, or B-tree page/freelist work. It is limited to row-by-row sorter yield diagnostics over already sorted NULL/collation/affinity order.

## Dependency Closure

No new support component is needed. The implementation reuses lane-local VDBE sort comparison and cursor primitives.
