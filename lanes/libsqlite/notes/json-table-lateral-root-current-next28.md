# JSON table lateral root current-next28

Slice: `yield-sqlite-json-table-lateral-root-current-next28`.

Behavior: parser-level `json_each()` / `json_tree()` sources can use the
current joined row to compute the hidden `root` argument for a later JSON table
source. This covers roots derived from a prior JSON row's `fullkey` / `atom`,
host metadata rows, concatenated path expressions, nested current-root chains,
LEFT JOIN NULL extension, JSONB host values, grouped aggregates, CTE wrapping,
and malformed dynamic root guards. Dynamic SQL NULL roots now yield an empty
JSON table rowset instead of throwing a root-type error.

Focused test delta: `+64` TestRunner PASS lines in
`SQLiteJsonTableLateralRootCurrentNext28Test.php`.

Expected `phpPass` delta: `+64`, from `9342` to `9406`.

`benchmarkDenominator.mapped` unchanged; this is focused parser/executor
coverage inspired by SQLite's implicit lateral table-valued function behavior,
not a fresh hydrated upstream runner mapping.

Verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableLateralRootCurrentNext28Test.php
Focused test run: 1 selected test files (root lock skipped)
64 PASS lines
1 test files, 70 assertions, 0 failures
```

Application smoke:

```text
php lanes/libsqlite/examples/application-json-table-lateral-root-current-next28.php --self-test
application-json-table-lateral-root-current-next28 self-test passed
```

Dependency closure: no new support component is needed. The slice reuses the
lane-local SELECT SQL parser/executor, JSON table planner, JSONB codec, dynamic
row callbacks, and Application copied `wp_options` row fixtures.

Non-overlap: avoids accepted JSON table cursor/source/hidden/visible
constraint work, JSON host joins, grouped JSON rows, SELECT SQL JOIN/GROUP BY/
subquery/expression ORDER BY/comma-LIMIT clusters, Unicode GLOB, VFS writer/
sync/lock/rollback clusters, WAL byte truncation/checkpoint transaction
clusters, and B-tree page-move/root-collapse/overflow freelist clusters.
