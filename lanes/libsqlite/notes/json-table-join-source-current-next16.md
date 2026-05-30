# JSON Table Join Source Current Next16

- Slice: parser-level `json_each()` / `json_tree()` joins whose arguments are evaluated from the current left/source row.
- Behavior: dynamic JSON table joins now preserve qualified `rowid`, `_rowid_`, and `oid` aliases for joined JSON rows, keep SQL NULL/JSONB empty-row behavior, and surface malformed dynamic text JSON as an error instead of silently producing an empty inner rowset.
- Focused evidence: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableJoinSourceCurrentNext16Test.php` passes with `1 test files, 31 assertions, 0 failures` and 31 PASS lines.
- Application smoke: `php lanes/libsqlite/examples/application-json-each-comma-join.php` reports copied `wp_options` JSON expanded through comma and LEFT JSON table joins.
- Non-overlap: avoids accepted JSON hidden/visible constraint extraction, JSON table cursor/source wrappers, JSON host-row materializer-only helpers, SELECT SQL JOIN text, and recent VFS/WAL/B-tree storage clusters.
- Dependency closure: no new support component is needed; the slice reuses the existing bounded `SQLiteSelectSql`, `SQLiteJsonTablePlan`, and native JSON table row helpers.
