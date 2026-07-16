# Savepoint nested rollback release current source next116

This slice adds `SQLiteSavepointStack::rollbackToCurrentSourceThenRelease116()`
for the SQLite behavior where `ROLLBACK TO` a nested savepoint rewinds the
current database image and WAL frame boundary while leaving the named savepoint
active, then `RELEASE` closes that empty target frame and leaves the parent
transaction/savepoint active for retry or commit.

The method verifies the current database page images before rollback so stale
pager sources are rejected, reports restored page images, discarded WAL frames,
the post-rollback and post-release stack names, and the remaining pending page
/ WAL state.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteSavepointNestedRollbackReleaseCurrentSourceNext116Test.php`
- `php lanes/libsqlite/examples/application-savepoint-nested-rollback-release-current-source-next116.php --self-test`
- `php -l lanes/libsqlite/src/SQLiteSavepointStack.php`
- `php -l lanes/libsqlite/tests/SQLiteSavepointNestedRollbackReleaseCurrentSourceNext116Test.php`
- `php -l lanes/libsqlite/examples/application-savepoint-nested-rollback-release-current-source-next116.php`
- `git diff --check -- lanes/libsqlite`

Non-overlap: this does not repeat accepted savepoint page-image rollback,
savepoint RELEASE corpus, WAL byte truncation, VFS savepoint rollback apply,
pager statement-journal savepoint handling, master-journal savepoints, or
transaction savepoint trigger rollback. The new behavior is the current-source
verification and combined nested `ROLLBACK TO` plus immediate `RELEASE` stack
transition for copied Application option import retries.

Dependency closure: no new support component is needed. The slice reuses
existing native PHP savepoint page-image, WAL-frame, and release bookkeeping.
