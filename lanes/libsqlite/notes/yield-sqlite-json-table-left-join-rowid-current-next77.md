# JSON Table LEFT JOIN Rowid Current/Next77

- Scope: parser-level `json_each()` / `json_tree()` dynamic LEFT JOINs where the `ON` predicate references `rowid`, `_rowid_`, or `oid`.
- Behavior: rowid aliases are normalized to the JSON table `id` visible constraint for pushdown; LEFT JOIN null-extension still supplies nullable rowid aliases for empty arrays, SQL NULL inputs, impossible rowid predicates, and JSONB-backed copied `wp_options` rows.
- Application smoke: `examples/application-json-table-left-join-rowid-current-next77.php` previews plugin setting flags selected by JSON table rowid aliases without ext/sqlite.
- Focused evidence: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableLeftJoinRowidCurrentNext77Test.php` passes with 41 PASS lines / 47 assertions.
- Non-overlap: avoids accepted JSON table cursor/source/hidden/visible constraint clusters by targeting rowid alias predicates inside dynamic LEFT JOIN `ON` clauses and their null-extension regression path.
- Dependency closure: no new support component is needed; this reuses the existing native PHP JSON table planner, SELECT SQL executor, JSONB value, and row-array join support.
