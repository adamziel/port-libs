# Pager Hot Journal Super Current Next70

This slice adds `SQLitePagerHotJournalSuperCurrentNextPlan` and
`SQLiteVfsFileWriter::applyHotJournalSuperRecovery()` for the attached-database
hot rollback-journal recovery edge where rollback journals require a
super-journal.

Behavior covered:

- Missing super-journal preserves current dirty database images and rollback
  journals.
- Present super-journal recovers each listed attached database from its hot
  rollback journal, truncates pages back to the initial database size, deletes
  recovered attached journals, deletes the super-journal, and syncs the parent
  directory.
- Partial super-journal lists recover listed databases while preserving
  unlisted journals/images.
- Reserved-lock and invalid input gates prevent unsafe recovery.
- Application smoke applies the recovery to copied `wp_options` /
  `wp_sitemeta`-style attached database images through native PHP file handles.

Verification:

```sh
php -l lanes/libsqlite/src/SQLitePagerHotJournalSuperCurrentNextPlan.php
php -l lanes/libsqlite/src/SQLiteVfsFileWriter.php
php -l lanes/libsqlite/tests/SQLitePagerHotJournalSuperCurrentNext70Test.php
php -l lanes/libsqlite/examples/application-hot-journal-super-current-next.php
php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerHotJournalSuperCurrentNext70Test.php
php lanes/libsqlite/examples/application-hot-journal-super-current-next.php
git diff --check -- lanes/libsqlite
```

Focused test output:

```text
1 test files, 74 assertions, 0 failures
```

Non-overlap: avoids accepted super-journal commit, rollback-journal
commit/apply, hot rollback-journal single-database recovery, hot-journal WAL
recovery/visibility, WAL reader-pin restart/truncate handoff, savepoint
statement/release current-next slices, VFS writer/sync/lock clusters, B-tree
pointer-map/freeblock/overflow clusters, JSON table source/cursor/constraint
clusters, SELECT SQL text/subquery/group/order clusters, LIKE current-next, and
Unicode GLOB behavior. The new surface is current/next pager recovery for hot
rollback journals that require a super-journal across attached databases.

Dependency closure: no new support component is needed. This reuses the native
rollback-journal parser/recovery and existing VFS file-handle write
application.
