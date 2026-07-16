# JSON Table Generated Rowid Order Current Source Next147

Slice: `json-table-generated-rowid-order-current-source-next147`.

Behavior: adds `SQLiteJsonTablePlan::currentSourceGeneratedRowidOrderNext147()`.
It composes the accepted generated-hidden rowid cost planner with generated
ORDER BY terms, then reports ordered rowids, generated sort keys, sort
penalties, current/next transitions, and reprepare reasons for rowid-scoped
`json_tree()` scans over copied Application option JSON.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedRowidOrderCurrentSourceNext147Test.php`
- `php lanes/libsqlite/examples/application-json-table-generated-rowid-order-current-source-next147.php`

Dependency closure: no new support component is needed. This reuses the
lane-local JSON table planner, generated hidden constraint evaluation, rowid
alias matching, JSON path extraction, JSONB input handling, and current-source
transition helpers.

Non-overlap: avoids accepted JSON table cursor/source/hidden/visible
constraints, lateral rowid, generated path/order/cost, generated hidden rowid
cost next142, hidden-path generated next143, JSON aggregate/window, SQL
executor, VFS/WAL, B-tree, and encoding clusters. The new surface is the
intersection of generated hidden predicates, hidden rowid aliases, and
generated sort order at the current-source/next-source boundary.

Next task: wire this planner result into broader parser-level JSON table
executor ordering only if it can add a non-overlapping current-source test
cluster.
