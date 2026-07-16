# SQLite Savepoint Rollback Release Edge Next12

Status: focused PHP corpus growth for upstream savepoint name lookup and duplicate-name edge behavior.

Changes:

- Updated `SQLiteSavepointStack` savepoint lookup to match SQLite's case-insensitive savepoint name behavior.
- Added `SQLiteSavepointRollbackReleaseEdgeNext12Test.php` with 30 independent PASS cases covering mixed-case `ROLLBACK TO` / `RELEASE`, duplicate savepoint names resolving to the most recent match, RELEASE merging into the earlier duplicate, post-rollback WAL frame reuse, and case-insensitive transaction release.
- Added `application-savepoint-rollback-release-edge-next12.php` to smoke a copied `wp_options` plugin-settings import where mixed-case application savepoint labels still roll back and release the intended SQLite savepoint.

Evidence:

- `php -l lanes/libsqlite/src/SQLiteSavepointStack.php` -> no syntax errors.
- `php -l lanes/libsqlite/tests/SQLiteSavepointRollbackReleaseEdgeNext12Test.php` -> no syntax errors.
- `php -l lanes/libsqlite/examples/application-savepoint-rollback-release-edge-next12.php` -> no syntax errors.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteSavepointRollbackReleaseEdgeNext12Test.php` -> 1 test files, 30 assertions, 0 failures, 30 PASS lines.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteSavepointRollbackReleaseEdgeNext12Test.php lanes/libsqlite/tests/SQLiteSavepointReleaseCorpusTest.php lanes/libsqlite/tests/SQLitePagerWalSavepointCorpusTest.php` -> 3 test files, 90 assertions, 0 failures.
- `php lanes/libsqlite/examples/application-savepoint-rollback-release-edge-next12.php --self-test` -> printed mixed-case rollback/release summary and exited 0.
- `jq empty lanes/libsqlite/lane-status.json` -> passed.
- `git diff --check -- lanes/libsqlite` -> passed.

Non-overlap:

- Avoids accepted savepoint page-image rollback, WAL byte truncation, VFS savepoint rollback application, rollback-journal commit/apply, super-journal commit, WAL checkpoint transactions, VFS writer/sync/lock clusters, and B-tree/JSON/SELECT high-yield batches.

Dependency closure:

- No new support component is needed. This reuses the lane-local `SQLiteSavepointStack` model and adds countable corpus coverage for upstream savepoint name semantics.

Next:

- Continue with non-overlapping pager/VFS transaction application, WAL durability, SQL planner/executor behavior, or release/all-suite countability blockers on current accepted HEAD.
