# VDBE Window GROUPS Value EXCLUDE CURRENT ROW Current/Next52

Status delta:

- Added `SQLiteVdbeWindowAggregateCursor::currentValueFrameSummary()` and
  `currentNextValueFrameSummary()` for VDBE-style value-window peeks.
- Added 50 focused PASS cases for `first_value()`, `last_value()`, and
  `nth_value()` over `GROUPS BETWEEN CURRENT ROW AND N FOLLOWING EXCLUDE
  CURRENT ROW`, including peer groups, next-row recomputation, SQL FILTER
  truthiness, partition boundaries, empty frames, custom rowid columns, and EOF
  guards.
- Added a Application smoke for copied `wp_options` diagnostics that previews
  current/next GROUPS value frames without requiring ext/sqlite.
- Updated `lane-status.json` `phpPass` from `19277` to `19327` by the verified
  focused PASS-line delta.

Focused verification:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteVdbeWindowGroupsValueExcludeCurrentNext52Test.php
# 1 test files, 50 assertions, 0 failures

php lanes/libsqlite/examples/application-vdbe-window-groups-value-exclude-current-next52.php --self-test
# application-vdbe-window-groups-value-exclude-current-next52 self-test passed
```

Non-overlap:

This avoids accepted aggregate `GROUPS` EXCLUDE/FILTER current-next37/current-next49,
parser-level SELECT window text, next48 generic value-frame helpers, JSON table
window/source/cursor/constraint work, VFS writer/lock/sync/rollback clusters,
WAL byte/checkpoint/savepoint clusters, B-tree page move/root-collapse/overflow
freelist work, Unicode GLOB, and batch23 metadata/planner/VDBE work. The
narrow behavior is VDBE current/next value-window summary peeks over GROUPS
frames after `EXCLUDE CURRENT ROW`.

Dependency closure:

No new support component is needed. This reuses the existing native PHP VDBE
window cursor, sort comparator, GROUPS frame selection, EXCLUDE handling, and
SQL truthiness primitives.
