# VDBE Sorter DISTINCT Group Current/Next 21

## Behavior

- Added `SQLiteVdbeSorterDistinctGroupCursor` for the VDBE loop shape where a sorted row stream is consumed through current/next group boundaries.
- Each group builds a fresh `SQLiteVdbeAggregateDistinctCursor`, so DISTINCT aggregate state resets per GROUP BY key while preserving filter, affinity, collation, NULL placement, and EOF behavior.
- The Application smoke uses copied `wp_options`-style rows grouped by `autoload` and option kind.

## Evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteVdbeSorterDistinctGroupCurrentNext21Test.php`
  - `1 test files, 36 assertions, 0 failures`
  - 36 new focused PASS lines.
- `php lanes/libsqlite/examples/application-vdbe-sorter-distinct-group.php`
  - Emits grouped copied `wp_options` DISTINCT summaries without requiring `ext/sqlite`.

## Status Delta

- `lane-status.json` `phpPass`: `7262 -> 7298`.
- `phpFail`: `0`.
- `benchmarkDenominator.mapped`: unchanged; this is focused native PHP behavior coverage, not a newly mapped upstream inventory unit.

## Non-Overlap

This slice does not repeat accepted parser-level `GROUP BY`/`HAVING` SQL text, composite GROUP BY execution, JSON table source/cursor work, VFS writer/lock/sync work, WAL savepoint byte truncation, B-tree page moves/root collapse/overflow freelist release, expression ORDER BY, or Unicode GLOB range behavior. It is limited to VDBE-style sorter current/next group iteration with per-group aggregate DISTINCT cursor state.

## Dependency Closure

No new support component is needed. The implementation reuses existing lane-local sorter comparison and aggregate DISTINCT cursor primitives.
