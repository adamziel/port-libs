# UPDATE/DELETE RETURNING Corpus Next

2026-05-27 isolated slice `yield-sqlite-update-delete-returning-corpus-next`.

- Behavior: added bounded `SQLiteUpdateDeleteLimitPlan::returningRows()` for UPDATE/DELETE RETURNING-style row projection after mutation selection.
- Upstream basis: SQLite `RETURNING` returns one row for each row deleted or updated; DELETE returns pre-delete values, UPDATE returns post-update values. This corpus exercises that behavior over copied `wp_options` rows without requiring ext/sqlite.
- Focused PHP evidence: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteUpdateDeleteReturningCorpusTest.php` reports 1 test file / 33 assertions / 0 failures.
- Application smoke: `php lanes/libsqlite/examples/application-update-delete-returning-corpus.php` reports deleted transient rows, remaining option ids, and updated autoload rows with RETURNING aliases/computed values.
- Non-overlap: does not repeat accepted UPDATE/DELETE ORDER BY LIMIT/OFFSET selection, UPDATE FROM current-conflict behavior, SELECT SQL text dispatch, JSON table cursor/source work, WAL/VFS apply slices, B-tree page moves, or accepted UTF/GLOB/collation clusters; this slice covers the returned mutation rowset after bounded row selection.
- Dependency closure: no new support component is needed; the slice reuses the existing row-array UPDATE/DELETE limit planner and SELECT result ordering helpers.
- Next task: parser-level UPDATE/DELETE SQL text can wire `RETURNING` clauses into this projection primitive once broader DML SQL execution is owned by a future executor slice.
