# Pager Savepoint Release Next Current-Next68

Status: focused PHP behavior growth for the pager edge `ROLLBACK TO sp; RELEASE sp; SAVEPOINT next` followed by the next write.

This slice adds `SQLiteSavepointStack::rollbackReleaseAndBeginNextSavepoint68()`. It models the SQLite savepoint transition where rollback-to clears target/nested statement journals and WAL frames, release closes the still-active target savepoint, and the next savepoint write starts at the retained WAL frame prefix.

Application path: `application-pager-savepoint-release-next-current-next68.php` covers a copied `wp_options` plugin import that rolls back a failed plugin settings savepoint, releases it, and retries in the next savepoint without keeping stale target statement journals.

Verification:

```sh
php -l lanes/libsqlite/src/SQLiteSavepointStack.php
php -l lanes/libsqlite/tests/SQLitePagerSavepointReleaseNextCurrentNext68Test.php
php -l lanes/libsqlite/examples/application-pager-savepoint-release-next-current-next68.php
php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerSavepointReleaseNextCurrentNext68Test.php
php lanes/libsqlite/examples/application-pager-savepoint-release-next-current-next68.php --self-test
```

Results:

```text
No syntax errors detected in lanes/libsqlite/src/SQLiteSavepointStack.php
No syntax errors detected in lanes/libsqlite/tests/SQLitePagerSavepointReleaseNextCurrentNext68Test.php
No syntax errors detected in lanes/libsqlite/examples/application-pager-savepoint-release-next-current-next68.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 64 assertions, 0 failures
application-pager-savepoint-release-next-current-next68 self-test passed
```

Expected dashboard movement: `phpPass` +64, from 25285 to 25349. `benchmarkDenominator.mapped` is unchanged because this is focused native pager behavior coverage, not a newly mapped upstream inventory unit.

Non-overlap: this avoids accepted pager savepoint statement-current-next66, savepoint page-image rollback, WAL byte truncation, VFS savepoint rollback application, rollback-journal commit/apply, super-journal commit, WAL checkpoint transaction/current-reader slices, VFS writer/sync/lock clusters, B-tree pointer-map/freeblock/overflow clusters, JSON table source/cursor/constraint clusters, SELECT SQL text/subquery/group/order clusters, and Unicode GLOB behavior. The new surface is release-after-rollback savepoint closure plus the next savepoint write from the retained WAL prefix.

Dependency closure: no new support component is needed. The slice reuses lane-local savepoint stack, statement journal, page-image, and WAL frame bookkeeping primitives.

Next task: continue with broader pager/VFS transaction application or another non-overlapping WAL durability edge; avoid another savepoint wrapper unless it applies a distinct pager state transition.
