# full-run-parity-application-wal-rollback-json-dynamic-20260530T222330Z-0

This slice adds a generic dynamic application parity matrix for JSON import
failure under WAL rollback. It reuses the existing native JSON mutation,
statement-journal, savepoint image rollback, and WAL byte truncation paths, then
varies tenant ids, page sizes, page numbers, WAL frame counts, JSON text, and
JSONB payloads across 16 deterministic scenarios.

Focused behavior:

- `SQLiteJsonImportRollbackWalPlan::dynamicParityScenarios()` builds dynamic
  page-aligned database images and WAL byte streams, runs each scenario through
  the real rollback planner, and returns the computed plans for diagnostics.
- `SQLiteApplicationWalRollbackJsonDynamicParityTest.php` adds 402 focused
  PASS cases covering transaction/savepoint names, WAL frame counts, byte
  truncation, restored page images, discarded WAL frames, tenant preservation,
  JSONB/text parity, and malformed JSON rollback isolation.
- `application-wal-rollback-json-dynamic-parity.php --self-test` provides a
  generic application smoke without domain-specific libsqlite API names.

Non-overlap:

This does not repeat accepted WAL byte truncation, VFS savepoint rollback
application, rollback-journal apply/commit, pager WAL dynamic corpus, JSON table
cursor/source/hidden/visible constraints, JSON105/106/109 upstream dynamic
coverage, or application `current next38` static rollback rows. The new surface
is deterministic dynamic full-run parity over the existing application
JSON-import WAL rollback composition path.

Verification:

- `php -l lanes/libsqlite/src/SQLiteJsonImportRollbackWalPlan.php`
- `php -l lanes/libsqlite/src/SQLiteJsonImportSavepointPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteApplicationWalRollbackJsonDynamicParityTest.php`
- `php -l lanes/libsqlite/examples/application-wal-rollback-json-dynamic-parity.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteApplicationWalRollbackJsonDynamicParityTest.php lanes/libsqlite/tests/SQLiteApplicationImportRollbackWalJsonCurrentNext38Test.php lanes/libsqlite/tests/SQLiteApplicationJsonImportSavepointCurrentNext48Test.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
- `php lanes/libsqlite/examples/application-wal-rollback-json-dynamic-parity.php --self-test`
- `git diff --check -- lanes/libsqlite`

Dependency closure:

No new support component is needed. The slice reuses existing native PHP JSON,
JSONB, savepoint, WAL-header, and TestRunner support. Full SQLite release/all
runner parity remains unclaimed.
