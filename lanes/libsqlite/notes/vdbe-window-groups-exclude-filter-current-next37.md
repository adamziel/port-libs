# VDBE window GROUPS EXCLUDE/FILTER current-next37

## Behavior

- Added `SQLiteVdbeWindowAggregateCursor` support for `GROUPS` frames.
- Added cursor-level `EXCLUDE CURRENT ROW`, `EXCLUDE GROUP`, and `EXCLUDE TIES` handling before aggregate FILTER truthiness.
- Preserved ROWS/RANGE behavior and allowed empty excluded frames in summaries with `frameStart`/`frameEnd` as `null`.

## Focused evidence

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteVdbeWindowGroupsExcludeFilterCurrentNext37Test.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 50 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteVdbeWindowGroupsExcludeFilterCurrentNext37Test.php lanes/libsqlite/tests/SQLiteVdbeWindowFrameGroupsCurrentNext29Test.php
Focused test run: 2 selected test files (root lock skipped)
2 test files, 96 assertions, 0 failures
```

```text
php lanes/libsqlite/examples/application-vdbe-window-groups-exclude-filter-current-next37.php
Emits copied wp_options window diagnostics for:
sum(bytes) FILTER (WHERE include) OVER (ORDER BY bytes, option_name GROUPS BETWEEN CURRENT ROW AND 1 FOLLOWING EXCLUDE CURRENT ROW)
```

## Counter delta

- `phpPass`: `12903 -> 12953` from 50 new focused PASS assertions in `SQLiteVdbeWindowGroupsExcludeFilterCurrentNext37Test.php`.
- `benchmarkDenominator.mapped`: unchanged; this patch adds focused PHP coverage only.

## Non-overlap

This patch does not repeat accepted parser-level GROUP BY/HAVING SQL text, JSON table cursor/source/hidden/visible constraint work, VFS sync/rollback/lock/write clusters, B-tree page move/root collapse/overflow release clusters, or accepted window peer/range SQL text helpers. The narrower behavior is the VDBE-style aggregate cursor applying GROUPS peer frames with EXCLUDE and FILTER composition.

## Dependency closure

No new support component is needed. The slice reuses existing native PHP VDBE sort comparison, numeric/text aggregate helpers, and SQL truthiness handling.
