# Application Schema JSON WAL Import Current Next41

This slice adds `SQLiteSchemaJsonWalImportPlan`, a bounded current/next
orchestration layer for copied Application imports that need schema objects and
JSON `wp_options` mutations represented as one WAL-yielding transaction.

Focused behavior:

- Reuses the accepted schema bulk import and JSON import savepoint planners.
- Emits committed schema and JSON WAL frame metadata while keeping failed JSON
  statement frames out of the committed WAL sequence.
- Reports yielded schema/json events, schema/data cookie deltas, dirty pages,
  checkpoint admission, and durable commit ordering for Application import
  diagnostics.

Non-overlap:

- Does not repeat parser-level JSON table sources/cursors, JSON hidden/visible
  constraints, VFS writer/lock/sync apply, rollback-journal commit, WAL
  checkpoint transaction planning, savepoint byte truncation, or existing schema
  bulk import/savepoint helpers. This layer composes those helpers for the
  assigned schema+JSON+WAL import current/next slice.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteSchemaJsonWalImportCurrentNext41Test.php`
- `php lanes/libsqlite/examples/application-schema-json-wal-import-current-next41.php`
- `php -l lanes/libsqlite/src/SQLiteSchemaJsonWalImportPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteSchemaJsonWalImportCurrentNext41Test.php`
- `php -l lanes/libsqlite/examples/application-schema-json-wal-import-current-next41.php`
- `git diff --check -- lanes/libsqlite`

Dependency closure:

No new support component is needed. The slice reuses existing native PHP
schema-bulk-import, JSON mutation/savepoint, and WAL frame planning components.
