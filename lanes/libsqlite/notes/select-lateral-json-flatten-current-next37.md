# SELECT lateral JSON flatten current-next37

Slice: `yield-sqlite-select-lateral-json-flatten-current-next37`.

Behavior: adds a focused parser/executor corpus for SQLite's implicit lateral
table-valued function behavior in comma-form `FROM` lists. The tests cover
`wp_options, json_tree(...), json_each(...)` flattening where later JSON table
roots read the current host row or a prior JSON row's `fullkey`/`atom`, plus
JSONB inputs, grouped aggregates, CTE wrapping, scalar JSON functions against
flattened paths, empty/null rowsets, malformed dynamic guards, and current/next
dynamic-row plan callbacks.

Focused test delta: `+52` TestRunner PASS lines in
`SQLiteSelectLateralJsonFlattenCurrentNext37Test.php`.

Expected `phpPass` delta: `+52`, from `12903` to `12955`.

`benchmarkDenominator.mapped` unchanged; this is focused PHP parser/executor
coverage inspired by upstream SQLite JSON table-valued function flattening, not
a fresh hydrated upstream runner mapping.

Verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteSelectLateralJsonFlattenCurrentNext37Test.php
Focused test run: 1 selected test files (root lock skipped)
52 PASS lines
1 test files, 54 assertions, 0 failures
```

Application smoke:

```text
php lanes/libsqlite/examples/application-select-lateral-json-flatten-current-next37.php --self-test
application-select-lateral-json-flatten-current-next37 self-test passed
```

Dependency closure: no new support component is needed. The slice reuses the
lane-local `SQLiteSelectSql` comma-source normalization, dynamic JSON table row
callbacks, JSONB codec, `SQLiteJsonTablePlan`, and copied `wp_options` row
fixtures.

Non-overlap: avoids accepted parser-level JSON table SELECT source/cursor/
hidden/visible-constraint clusters, accepted lateral-root current-next28
explicit JOIN coverage, JSON host joins, JSON grouped rows, SELECT SQL
subquery/GROUP BY/expression ORDER BY/comma-LIMIT clusters, Unicode GLOB, VFS
writer/sync/lock/rollback clusters, WAL byte truncation/checkpoint transaction
clusters, and B-tree page-move/root-collapse/overflow freelist clusters. The
new surface is comma-form flattening evidence for current/next dynamic JSON
table rows.
