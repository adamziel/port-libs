# VDBE window EXCLUDE/FILTER RANGE current-next51

2026-05-27 isolated slice `yield-sqlite-vdbe-window-exclude-filter-range-current-next51`.

## Behavior

- Added `SQLiteVdbeWindowAggregateCursor::currentYieldSummary()` for VDBE-style current/next RANGE frame diagnostics.
- The summary exposes raw RANGE frame rowids, rowids removed by `EXCLUDE CURRENT ROW`, `EXCLUDE GROUP`, or `EXCLUDE TIES`, FILTER-selected rowids/values, aggregate outputs, and next-row partition/peer state.
- Added focused coverage for `RANGE BETWEEN CURRENT ROW AND <n> FOLLOWING` over copied `wp_options`-style rows, including duplicate peers, SQL truthiness, NULL aggregate values, descending RANGE scans, and partition boundaries.

## Verification

```bash
php tools/run-tests.php lanes/libsqlite/tests/SQLiteVdbeWindowExcludeFilterRangeCurrentNext51Test.php
# Focused test run: 1 selected test files (root lock skipped)
# 1 test files, 53 assertions, 0 failures
```

Application smoke:

```bash
php lanes/libsqlite/examples/application-vdbe-window-exclude-filter-range-current-next51.php
```

## Status Delta

- `phpPass`: `18565 -> 18618` from 53 new focused PASS assertions in `SQLiteVdbeWindowExcludeFilterRangeCurrentNext51Test.php`.
- `benchmarkDenominator.mapped`: unchanged; no new upstream inventory unit mapped.
- Dependency closure: no new support component required. This reuses `SQLiteVdbeWindowAggregateCursor`, `SQLiteNumericAggregate`, `SQLiteTextAggregate`, and existing VDBE sort comparison helpers.

## Non-overlap

This avoids accepted GROUPS EXCLUDE/FILTER current-next37, value-window EXCLUDE current-next48, SELECT SQL grouped/window text, JSON table cursor/source/constraint work, WAL/VFS/B-tree accepted clusters, and release-runner evidence slices. The narrower behavior is RANGE frame current/next yield evidence with EXCLUDE-before-FILTER aggregate rows.
