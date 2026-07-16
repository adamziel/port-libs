# JSON table lateral rowid current-next81

This slice adds `SQLiteJsonTablePlan::lateralRowidCurrentNext81()` for lateral
`json_each()` / `json_tree()` host-row scans that need current/next rowid alias
materialization.

The behavior covers rowid, `_rowid_`, and `oid` aliases for each dynamic JSON
table row, LEFT JOIN null-extension when a current host row has no JSON matches,
host boundary changes, added/removed lateral rows, and current/next rowid
transition reasons for parser/VDBE-style yield loops.

Verification:

- `php -l lanes/libsqlite/src/SQLiteJsonTablePlan.php`
- `php -l lanes/libsqlite/tests/SQLiteJsonTableLateralRowidCurrentNext81Test.php`
- `php -l lanes/libsqlite/examples/application-json-table-lateral-rowid-current-next81.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableLateralRowidCurrentNext81Test.php`
- `php lanes/libsqlite/examples/application-json-table-lateral-rowid-current-next81.php --self-test`
- `git diff --check -- lanes/libsqlite`

Non-overlap: this avoids accepted JSON table cursor behavior, parser-level JSON
table SELECT/FROM source wiring, hidden and visible constraint pushdown, nested
LEFT JOIN rowid alias regressions, batch75 lateral constraint planner reprepare,
JSON aggregate/window behavior, JSONB CHECK admission, VFS/WAL/B-tree/SQL
executor clusters, and Unicode GLOB behavior. The new surface is the lateral
current/next rowid alias tape over copied Application option rows.

Dependency closure: no new support component is needed; this reuses existing
native PHP JSON table, JSONB, and path helpers.
