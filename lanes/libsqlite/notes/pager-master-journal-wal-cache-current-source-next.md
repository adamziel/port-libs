# Pager Master Journal WAL Cache Current Source Next129

## Behavior

This slice adds `SQLitePagerMasterJournalWalCacheCurrentSourceNextPlan` for
the current-source edge where a master-journal recovery restores database pages
before the pager reuses WAL page-cache entries. Any cached page image that
predates the master-journal recovery is reported as stale and, when refresh is
enabled, is replaced with the recovered current source before retry WAL appends
or checkpoint page writes are planned.

The WordPress smoke models a copied `wp_options` database recovery where stale
root/transient cache pages are refreshed before retrying option-row WAL appends.

## Evidence

Focused verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalWalCacheCurrentSourceNext129Test.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 77 assertions, 0 failures
```

PHP lint:

```text
php -l lanes/libsqlite/src/SQLitePagerMasterJournalWalCacheCurrentSourceNextPlan.php
php -l lanes/libsqlite/tests/SQLitePagerMasterJournalWalCacheCurrentSourceNext129Test.php
php -l lanes/libsqlite/examples/wordpress-pager-master-journal-wal-cache-current-source-next129.php
No syntax errors detected
```

WordPress smoke:

```text
php lanes/libsqlite/examples/wordpress-pager-master-journal-wal-cache-current-source-next129.php --self-test
wordpress-pager-master-journal-wal-cache-current-source-next129 self-test passed
```

## Non-Overlap

Avoids accepted pager cache-spill journal-mode, pager hot-journal savepoint
cache, pager statement-journal savepoint/master, WAL checkpoint transactions,
WAL byte truncation, VFS savepoint rollback apply, rollback-journal commit
apply, super-journal commit, VFS sync, and VFS writer/lock clusters. This
slice is specifically about invalidating or refreshing stale WAL page-cache
images after master-journal recovery and before the next WAL append/checkpoint
current-source read.

## Dependency Closure

No new support component is needed. The patch composes lane-local PHP page-image
planning with existing pager, master-journal, WAL-cache, and checkpoint
evidence boundaries.

## Dashboard Delta

Expected lane-local `phpPass` movement: `53324 -> 53401` from 77 new focused
PASS/assertion lines. Mapped upstream coverage is unchanged because this is
focused PHP behavior evidence, not a fresh manifest-backed upstream runner row.
