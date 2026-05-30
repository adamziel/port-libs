# VFS Temp Locking File-Control Current Source Next83

## Delta

- Added `SQLiteVfsTempLockingFileControlCurrentSourcePlan::currentSourceNext83()`.
- Covers current-source routing for temp/main/attached VFS temp handles:
  unqualified file-control and lock operations follow the current source,
  schema-qualified operations still target their named source, and
  non-delete temp handles reuse persisted controls after close/reopen.
- Added a Application smoke for copied `wp_options` temp statement-journal
  imports where temp source shadowing must not misroute main file-control
  state.

## Non-overlap

Avoids accepted VFS file-control state transitions, VFS file-control
persistence current-next75, temp-file lifecycle current-next73, temp lock
file-control persistence current-next76, VFS open/file-control locking,
process-backed file locks, VFS locked writer, file writer, rollback journal
apply, and sync apply. This slice is only the current-source routing layer for
temp/main/attached temp handles.

## Verification

Local focused verification:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteVfsTempLockingFileControlCurrentSourceNext83Test.php
# Focused test run: 1 selected test files (root lock skipped)
# 1 test files, 63 assertions, 0 failures

php lanes/libsqlite/examples/application-vfs-temp-locking-filecontrol-current-source-next83.php --self-test
# application-vfs-temp-locking-filecontrol-current-source-next83 self-test passed

php -l lanes/libsqlite/src/SQLiteVfsTempLockingFileControlCurrentSourcePlan.php
php -l lanes/libsqlite/tests/SQLiteVfsTempLockingFileControlCurrentSourceNext83Test.php
php -l lanes/libsqlite/examples/application-vfs-temp-locking-filecontrol-current-source-next83.php
# No syntax errors detected in all three changed PHP files

git diff --check -- lanes/libsqlite
```

## Dependency Closure

No new external support component is needed. The slice reuses bounded native
PHP VFS temp-file lifecycle semantics and adds current-source routing state in
lane-local PHP.
