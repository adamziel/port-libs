# Pager Journal Savepoint Hot Rollback Current Source Next118

Status: focused PHP behavior growth for pager rollback-journal hot recovery feeding the next savepoint retry from the recovered current source.

## Behavior

- Added `SQLitePagerJournalSavepointHotRollbackCurrentSourceNextPlan`.
- A hot rollback journal is recovered first, using the current database and current rollback-journal bytes.
- The following savepoint retry captures before-images from the recovered current source, not the stale dirty page cache that existed before hot rollback.
- `ROLLBACK TO` the retry savepoint restores those recovered current-source images and rejects stale before-image input, reserved-lock blockers, and missing super-journal blockers.

## Verification

```bash
php -l lanes/libsqlite/src/SQLitePagerJournalSavepointHotRollbackCurrentSourceNextPlan.php
php -l lanes/libsqlite/tests/SQLitePagerJournalSavepointHotRollbackCurrentSourceNext118Test.php
php -l lanes/libsqlite/examples/application-pager-journal-savepoint-hot-rollback-current-source-next118.php
php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerJournalSavepointHotRollbackCurrentSourceNext118Test.php
php lanes/libsqlite/examples/application-pager-journal-savepoint-hot-rollback-current-source-next118.php --self-test
git diff --check -- lanes/libsqlite
```

Focused result: `1 test files, 68 assertions, 0 failures`.

## Application Smoke

`application-pager-journal-savepoint-hot-rollback-current-source-next118.php` models a copied `wp_options` database after a crashed plugin import. The smoke proves that the hot rollback journal restores the clean `active_plugins` page before a retry savepoint, and that rolling back the retry savepoint returns to that clean recovered page rather than the dirty crashed image or retry write.

## Non-overlap

This avoids accepted master-journal hot rollback next89, WAL hot-journal savepoint replay next87/next91, pager cache-spill savepoint next114, statement-journal WAL savepoint next112, VFS savepoint rollback apply, rollback-journal commit/apply, super-journal commit, WAL byte truncation, WAL checkpoint transactions, and pager master-journal savepoint current-source next108. The new surface is the rollback-journal pager composition point where a hot rollback establishes the current source used by the next savepoint before-image capture and rollback.

## Dependency Closure

No new support component is needed. This reuses native PHP rollback-journal parsing/checksum recovery plus existing savepoint page-image semantics.

Next task: continue with broader pager/VFS transaction application or another non-overlapping WAL/pager durability edge; avoid another savepoint wrapper unless it applies a distinct current-source transition.
