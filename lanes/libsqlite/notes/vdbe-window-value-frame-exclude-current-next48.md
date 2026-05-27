# VDBE Window Value Frame EXCLUDE CURRENT ROW Next48

## Behavior

- Added frame-aware `SQLiteVdbeWindowAggregateCursor::firstValue()`, `lastValue()`, and `nthValue()` helpers for VDBE-style value window functions.
- The value helpers reuse the existing partition/order/frame machinery, so `ROWS`, `RANGE`, `GROUPS`, `EXCLUDE CURRENT ROW`, `EXCLUDE GROUP`, `EXCLUDE TIES`, SQL truthy filters, empty frames, and partition tails all resolve through the same current-frame path as aggregate window cursors.
- `drainSummaries()` now reports `firstValue`, `lastValue`, and the second `nthValue` preview for lane diagnostics.

## Focused Evidence

Command:

```bash
php tools/run-tests.php lanes/libsqlite/tests/SQLiteVdbeWindowValueFrameExcludeCurrentNext48Test.php
```

Output:

```text
Focused test run: 1 selected test files (root lock skipped)
1 test files, 63 assertions, 0 failures
```

The new file contributes 63 focused PASS lines for the next accepted libsqlite status update.

## Non-Overlap

This slice does not repeat accepted aggregate window EXCLUDE/FILTER behavior, parser-level SELECT window text, JSON table windows, GROUPS/RANGE aggregate frames, or VDBE sorter EXCLUDE CURRENT ROW aggregates. It is limited to VDBE value-window frame reads for `first_value`, `last_value`, and `nth_value` over already-sorted cursor frames.

## Dependency Closure

No new support component is needed. The patch reuses the existing native PHP VDBE window cursor, sorter comparison, frame, and filter primitives.
