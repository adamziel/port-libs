# SQLite Savepoint RELEASE Corpus Next6

Status: focused PHP corpus growth for upstream savepoint `RELEASE` semantics.

Changes:

- Added `SQLiteSavepointReleaseCorpusTest.php` with 30 independent PASS cases covering nested `RELEASE`, outer transaction release-as-commit, merged dirty page/WAL-frame state, first page-image preservation for later rollback, and invalid/released savepoint guards.
- Added `application-savepoint-release-corpus-next.php` to smoke a copied `wp_options` plugin-settings import where a successful nested savepoint release merges autoload/cache writes into the parent transaction before commit.

Evidence:

- `php -l lanes/libsqlite/tests/SQLiteSavepointReleaseCorpusTest.php` -> no syntax errors.
- `php -l lanes/libsqlite/examples/application-savepoint-release-corpus-next.php` -> no syntax errors.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteSavepointReleaseCorpusTest.php` -> 1 test files, 30 assertions, 0 failures, 30 PASS lines.
- `php lanes/libsqlite/examples/application-savepoint-release-corpus-next.php` -> printed released savepoints `autoload_index` and `cache_warmup`, merged page numbers `[3,4]`, restored plugin/cache pages, discarded WAL frame indexes `[2,3,4]`, committed pages `[1,2,3,4]`, and inactive transaction after commit.

Non-overlap:

- Avoids accepted savepoint page-image rollback, WAL byte truncation, VFS savepoint rollback application, rollback-journal commit/apply, super-journal commit, WAL checkpoint transactions, VFS writer/sync/lock clusters, and the high-yield SQL/JSON/B-tree corpus batches.

Dependency closure:

- No new support component is needed. This reuses the lane-local `SQLiteSavepointStack` transaction/savepoint model and adds countable RELEASE corpus coverage around existing native PHP behavior.

Next:

- Continue with non-overlapping pager/VFS transaction application, WAL durability, or release/all-suite countability blockers on current accepted HEAD.
