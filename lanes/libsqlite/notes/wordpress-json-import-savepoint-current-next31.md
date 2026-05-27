# WordPress JSON Import Savepoint Current Next31

This slice adds `SQLiteWordPressJsonImportSavepointPlan`, a bounded WordPress
`wp_options` JSON import planner that records each JSON mutation as a SQLite
statement journal inside an outer savepoint. A malformed JSON option rolls back
only its statement page image and WAL frame while preserving the surrounding
savepoint and applied JSON mutations.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteWordPressJsonImportSavepointCurrentNext31Test.php`
  - `1 test files, 64 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteSavepointRollbackReleaseEdgeNext12Test.php lanes/libsqlite/tests/SQLiteWordPressJsonImportSavepointCurrentNext31Test.php`
  - `2 test files, 94 assertions, 0 failures`
- local example smoke: `php lanes/libsqlite/examples/wordpress-json-import-savepoint-current-next31.php`
  - reports `partial_rollback`, applied statements `enable_plugin` and
    `theme_accent`, failed statement `broken_payload`, rollback page `[4]`,
    and commit pages `[2,3]`.

Non-overlap:

- Avoids accepted WAL byte truncation, VFS savepoint rollback apply,
  rollback-journal commit/super-journal/sync paths, JSON table cursor/source/
  visible/hidden constraint pushdown, and parser-level SELECT SQL clusters.
- Reuses existing JSON mutation and `SQLiteSavepointStack` statement-journal
  primitives to cover the narrower WordPress JSON import savepoint behavior.

Dependency closure:

- No new support component is needed. The slice reuses existing native PHP JSON
  mutation, JSONB, JSON subtype, and savepoint statement-journal support.
