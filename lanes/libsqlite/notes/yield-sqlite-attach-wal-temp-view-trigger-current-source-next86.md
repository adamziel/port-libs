# attach-wal-temp-view-trigger-current-source-next86

- Behavior: `SQLiteAttachTempViewTriggerResolution::currentNextSourcePlan()`
  compares a prepared trigger/view source against the next schema catalog after
  WAL-backed schema DDL or temp schema DDL changes. It reports changed source
  fields, reprepare requirement, invalidated source schemas, and whether the
  change is scoped to WAL-backed main/attached schemas or connection-local temp
  schema state.
- Application path: copied `wp_options` import previews can now tell whether an
  `INSTEAD OF` view trigger prepared against current view columns must be
  reprepared before yielding rows after a plugin migration changes the view or
  trigger body in the next schema source.
- Focused evidence:
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteAttachWalTempViewTriggerCurrentSourceNext86Test.php`
  - `1 test files, 54 assertions, 0 failures`
  - `54` focused PASS lines.
- Application smoke:
  - `php lanes/libsqlite/examples/application-attach-wal-temp-view-trigger-current-source-next86.php --self-test`
  - `application-attach-wal-temp-view-trigger-current-source-next86 self-test passed`
- Non-overlap: avoids accepted ATTACH WAL/temp rollback routing, schema-cache
  reprepare/cache invalidation, trigger route planning, WAL byte materializing,
  JSON table/source work, VFS file-control, and batch82 attach view-cache
  current-source invalidation. This slice is only the source comparison for a
  prepared view trigger across current and next catalogs.
- Dependency closure: no new support component is needed. The slice reuses the
  existing lane-local attached schema catalog and trigger/view resolution
  parser.
