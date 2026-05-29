# attach-wal-temp-view-trigger-current-source-next101

- Behavior: `SQLiteAttachTempViewTriggerResolution::currentNextSourcePlan()`
  now keeps an active prepared view-trigger program on its current source when
  the next schema source drops the trigger, drops the target view, or detaches
  the attached schema. The plan reports `SQLITE_OK` for the current step and
  `abort-reset-and-reprepare` for the next step instead of throwing while
  comparing current and next sources.
- WordPress path: copied `wp_options` import previews can finish an active
  `INSTEAD OF` view-trigger row program while a plugin migration removes a
  main, temp, or attached archive trigger/view source in the next schema, then
  force `SQLITE_SCHEMA`-style reprepare before the next trigger step.
- Focused evidence:
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteAttachWalTempViewTriggerCurrentSourceNext101Test.php`
  - `1 test files, 43 assertions, 0 failures`
  - `43` focused PASS lines.
- WordPress smoke:
  - `php lanes/libsqlite/examples/wordpress-attach-wal-temp-view-trigger-current-source-next101.php --self-test`
  - `wordpress-attach-wal-temp-view-trigger-current-source-next101 self-test passed`
- Non-overlap: avoids accepted ATTACH WAL/temp rollback routing,
  schema-cache/trigger-cache reprepare, view-trigger current/next column
  comparisons, VFS write/sync/lock behavior, WAL checkpoint/savepoint
  materialization, JSON table source/cursor work, B-tree page/freelist
  clusters, and SQL SELECT text/subquery work. This slice only covers
  missing or unresolvable next trigger sources for an already prepared current
  view-trigger source.
- Dependency closure: no new support component is needed. The slice reuses the
  existing lane-local attached schema catalog and trigger/view source
  resolver.
