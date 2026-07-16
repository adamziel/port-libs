# json-table-lateral-rowid-hidden-current-source-next105

Status: focused PHP behavior growth for lateral JSON table hidden rowid current-source planning.

This slice adds `SQLiteJsonTablePlan::lateralRowidHiddenCurrentSourceNext105()`.
It bridges the accepted lateral rowid tape, keyed hidden current-source planner,
and rowid alias provenance so `json_each()` / `json_tree()` host-row scans can
report hidden `rowid` / `_rowid_` / `oid` constraints without treating host
reordering as a rowid tape mutation.

Behavior covered:

- keyed current/next host matching by Application option id;
- hidden rowid alias provenance normalized to JSON table `id`;
- per-host current and next rowid summaries;
- row-level rowid/fullkey/payload transitions;
- left-join null-extension with empty rowsets;
- JSONB next-source kind changes;
- stable host reorder reuse of the current rowid source tape;
- added/removed host diagnostics and invalid-input guards.

Verification:

```text
php -l lanes/libsqlite/src/SQLiteJsonTablePlan.php
No syntax errors detected in lanes/libsqlite/src/SQLiteJsonTablePlan.php

php -l lanes/libsqlite/tests/SQLiteJsonTableLateralRowidHiddenCurrentSourceNext105Test.php
No syntax errors detected in lanes/libsqlite/tests/SQLiteJsonTableLateralRowidHiddenCurrentSourceNext105Test.php

php -l lanes/libsqlite/examples/application-json-table-lateral-rowid-hidden-current-source-next105.php
No syntax errors detected in lanes/libsqlite/examples/application-json-table-lateral-rowid-hidden-current-source-next105.php

php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableLateralRowidHiddenCurrentSourceNext105Test.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 65 assertions, 0 failures

php lanes/libsqlite/examples/application-json-table-lateral-rowid-hidden-current-source-next105.php --self-test
application-json-table-lateral-rowid-hidden-current-source-next105 self-test passed
```

Dashboard delta: `phpPass` moves from `40110` to `40175` for the 65 verified
PASS lines. Mapped coverage is unchanged; this is a focused JSON table planner
current-source behavior under existing JSON table inventory.

Non-overlap: avoids accepted JSON table lateral rowid current-next81,
lateral hidden planner next90, rowid hidden constraint next99, lateral hidden
constraint next103, hidden/visible constraint extraction, JSON table SELECT
source/cursor work, recursive JSON materialization, JSON aggregate windows,
WAL/VFS/B-tree/encoding clusters, and release-runner evidence. The new surface
is the keyed lateral hidden rowid current-source summary and transition tape.

Dependency closure: no new support component is needed. The slice reuses
lane-local JSON table cursors, hidden constraint planning, rowid alias
provenance, JSONB decoding, and TestRunner infrastructure.
