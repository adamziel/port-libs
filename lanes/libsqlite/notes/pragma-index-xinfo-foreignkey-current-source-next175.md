# pragma-index-xinfo-foreignkey-current-source-next175

This slice adds row-level `PRAGMA foreign_key_list` current-source evidence
beside the existing `index_xinfo`, FK admission, action, and deferral
pagination.

Behavior:

- `SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext` wraps the accepted
  next173 current/next page and appends deterministic `foreign_key_list` rows
  for each FK column sequence.
- Composite foreign keys preserve `id` and `seq` ordering, child column names,
  resolved parent columns, parent affinity/collation, actions, match mode, and
  current/next source pagination.
- Cursor source IDs now include the FK-list column-sequence summary, so stale
  pagination cursors are rejected when FK column DDL changes.
- WordPress smoke coverage models copied multisite `wp_options` rows whose
  parent `wp_sites` and composite `wp_option_names` rows are repaired between
  current and next source snapshots.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNextTest.php`
  - `1 test files, 71 assertions, 0 failures`
  - `62` focused PASS lines
- `php lanes/libsqlite/examples/wordpress-pragma-index-xinfo-foreignkey-current-source-next175.php --self-test`
  - `wordpress-pragma-index-xinfo-foreignkey-current-source-next175 self-test passed`

Non-overlap:

This avoids accepted next173 deferrable/initially-deferred FK metadata,
next167 action decoration, and earlier FK admission/index_xinfo checks. The
new surface is `PRAGMA foreign_key_list` column-sequence rows as a paged
current-source artifact.

Dependency closure:

No new support component is needed. The slice reuses existing schema catalog,
`index_xinfo`, FK extraction, action, deferral, and current/next pagination
primitives.
