# JSON Table Lateral Hidden Rowid Current Source Next85

- Slice: `json-table-lateral-hidden-rowid-current-source-next85`.
- Behavior: bare `json_each AS alias` / `json_tree AS alias` joins now accept hidden `alias.json = current_source.column` and `alias.root = current_source.column_or_literal` constraints from the `ON` predicate. The hidden constraints are evaluated per current left row, omitted from the residual join predicate, and generated JSON rows still expose `rowid`, `_rowid_`, and `oid` aliases.
- Application path: copied `wp_options` plugin settings can join a JSON virtual table through hidden constraints instead of function arguments, preserving left-join null extension for options with empty arrays.
- Verification: run `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableLateralHiddenRowidCurrentSourceNext85Test.php`, syntax checks for changed PHP files, the Application smoke, and `git diff --check -- lanes/libsqlite`.
- Dependency closure: no new support component is needed; this reuses the existing native PHP SELECT executor, JSON table planner, JSON1/JSONB parser, and row-array join machinery.
- Non-overlap: avoids accepted JSON table SELECT-source wiring, hidden literal constraint extraction, visible constraint pushdown, cursor behavior, lateral rowid transition diagnostics, and prior left-join rowid alias coverage by covering hidden ON constraints whose values come from the current host source.
