# full-run-parity-application-wal-rollback-json-dynamic-20260531T034817Z-0

This handoff extends the accepted generic application WAL rollback JSON dynamic
parity surface with a preexisting-WAL-prefix branch. The previous dynamic
matrix only rolled failed JSON batches back to WAL frame zero. The new path
models committed frames that existed before the JSON import savepoint, then
verifies rollback truncates only the failed current JSON batch while preserving
the committed WAL prefix bytes and frame count.

Behavior:

- `SQLiteJsonImportSavepointPlan::plan()` accepts `pre_savepoint_wal_pages` and
  records those WAL frames before opening the JSON import savepoint.
- `SQLiteJsonImportRollbackWalPlan::dynamicPreexistingWalScenarios()` generates
  24 deterministic tenant/page-size/JSON-text/JSONB scenarios with 2-5
  preexisting frames plus a three-frame failed JSON batch.
- `SQLiteApplicationWalRollbackJsonDynamicParityTest.php` adds 413 focused
  TestRunner cases over the base generated test shape, covering rollback frame
  boundaries, byte truncation, preserved WAL prefix bytes, discarded current
  batch frames, tenant isolation, restored page images, and malformed JSON
  isolation.
- `application-wal-rollback-json-dynamic-parity.php --self-test` now reports
  the preexisting-WAL branch in the generic application smoke.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteApplicationWalRollbackJsonDynamicParityTest.php`
  passed with `1 test files, 1931 assertions, 0 failures`.
- Base generated case count: `1347`; current generated case count: `1760`;
  focused PASS-case growth: `+413`.
- `php -l lanes/libsqlite/src/SQLiteJsonImportSavepointPlan.php`
- `php -l lanes/libsqlite/src/SQLiteJsonImportRollbackWalPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteApplicationWalRollbackJsonDynamicParityTest.php`
- `php -l lanes/libsqlite/examples/application-wal-rollback-json-dynamic-parity.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteApplicationImportRollbackWalJsonCurrentNext38Test.php lanes/libsqlite/tests/SQLiteApplicationWalRollbackJsonDynamicParityTest.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
- `php lanes/libsqlite/examples/application-wal-rollback-json-dynamic-parity.php --self-test`
- `git diff --check -- lanes/libsqlite`

Non-overlap:

This does not repeat accepted static current-next38 rollback rows, the earlier
dynamic rollback-to-frame-zero branch, deferred failure behavior, retry
behavior, VFS savepoint rollback application, rollback-journal commit/apply,
WAL byte truncation primitives, pager WAL dynamic corpus, JSON table cursor or
constraint work, or JSON upstream constructor/path/error matrices. The new
surface is the application-level savepoint boundary where committed WAL prefix
frames survive a failed current JSON import batch.

Dependency closure:

No new support component is needed. This reuses native PHP JSON/JSONB mutation,
savepoint-stack WAL frame tracking, WAL byte parsing, page-image rollback, and
the existing focused TestRunner path. Full release/all-runner parity remains
open outside this slice.
