# full-run-parity-application-wal-rollback-json-dynamic-20260531T061227Z-0

This handoff extends the generic application WAL rollback JSON dynamic parity
surface with corrupt nonzero WAL header checksum handling. The existing branch
already rejected invalid magic, page-size mismatch, frame-header corruption,
frame checksum mismatch, partial tails, missing current-batch frames, and
preexisting committed WAL prefixes. The new branch rejects a checksummed WAL
stream before trusting current JSON batch rollback frames when either stored
header checksum word does not match the first 24 header bytes.

Behavior:

- `SQLiteJsonImportRollbackWalPlan::walState()` now validates nonzero WAL
  header checksums while preserving older zero-checksum synthetic WAL fixtures.
- Default `wal_bytes => null` remains a synthetic header-only WAL for legacy
  rollback diagnostics; explicit truncated or missing real WAL bytes still use
  current-batch frame existence validation.
- `SQLiteJsonImportRollbackWalPlan::dynamicHeaderChecksumMismatchScenarios()`
  generates 18 deterministic preexisting-WAL-prefix scenarios covering both
  header checksum fields, both page sizes, and 2-5 committed prefix frames.
- `SQLiteApplicationWalRollbackJsonDynamicParityTest.php` adds focused
  TestRunner coverage for the new rejection path, aligned WAL bytes, checksum
  offsets, full pre-rejection frame counts, zero-count validation, and
  deterministic small-batch output.
- `application-wal-rollback-json-dynamic-parity.php --self-test` reports the
  new header-checksum branch in the generic application smoke.

Verification:

- `php -l lanes/libsqlite/src/SQLiteJsonImportRollbackWalPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteApplicationWalRollbackJsonDynamicParityTest.php`
- `php -l lanes/libsqlite/examples/application-wal-rollback-json-dynamic-parity.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteApplicationWalRollbackJsonDynamicParityTest.php`
  passed with `1 test files, 4012 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteApplicationImportRollbackWalJsonCurrentNext38Test.php lanes/libsqlite/tests/SQLiteApplicationWalRollbackJsonDynamicParityTest.php`
  passed with `2 test files, 4062 assertions, 0 failures`.
- `php lanes/libsqlite/examples/application-wal-rollback-json-dynamic-parity.php --self-test`
  passed.

Non-overlap:

This does not repeat accepted application rollback-to-frame-zero, preexisting
WAL prefix rollback, deferred failure, retry-after-rollback, missing/partial WAL
tail, frame-header mismatch, frame-checksum mismatch, VFS savepoint rollback
application, rollback-journal commit/apply, pager WAL dynamic corpus, JSON
table cursor/constraint work, or upstream JSON constructor/path/error matrices.
The new surface is the application-level WAL header checksum guard before JSON
rollback planning uses a checksummed WAL stream.

Dependency closure:

No new support component is needed. This reuses native PHP WAL checksum,
JSON/JSONB mutation, savepoint-stack WAL frame tracking, WAL byte parsing, and
page-image rollback primitives. Full release/all-runner parity remains open
outside this slice.
