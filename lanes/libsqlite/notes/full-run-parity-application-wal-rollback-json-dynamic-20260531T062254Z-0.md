# full-run-parity-application-wal-rollback-json-dynamic-20260531T062254Z-0

Scope: app-WAL rollback JSON dynamic parity follow-up on accepted base
`68a3731675769814ce7d56857d9182ac7f8b3613`.

Behavior:

- `SQLiteJsonImportRollbackWalPlan` now computes chained WAL checksums when a
  successful retry materializes frames after a rollback.
- `walState()` advances the checksum seed across zero-checksum legacy prefix
  frames, so a later checksummed frame after a preserved prefix validates
  against the same chain SQLite uses.
- The focused parity test adds retry and preexisting-prefix retry assertions
  proving materialized success frames contain nonzero chained checksums and keep
  the expected page numbers.
- The generic application example reports the first materialized retry checksum
  pair and self-tests that it is not the old zero-checksum placeholder.

Verification:

- Red before fix:
  `php -d memory_limit=1024M tools/run-tests.php lanes/libsqlite/tests/SQLiteApplicationWalRollbackJsonDynamicParityTest.php`
  failed with `SQLite Application JSON import rollback WAL frame 3 checksum does
  not match the frame payload`.
- Green after fix:
  `php -d memory_limit=1024M tools/run-tests.php lanes/libsqlite/tests/SQLiteApplicationWalRollbackJsonDynamicParityTest.php`
  passed `1 test files, 4365 assertions, 0 failures`.

Dashboard delta:

- Adds 36 focused TestRunner PASS lines over the accepted app-WAL parity file.
- Expected `phpPass` movement: `2495399 -> 2495435`.
- Mapped denominator coverage remains `1589 / 1589`.

Non-overlap:

- Does not repeat rollback truncation, missing-tail detection, partial-tail
  detection, frame-header mismatch detection, frame-checksum mismatch detection,
  tenant collision rollback, inserted-setting rollback, WAL byte truncation,
  VFS writer/sync/lock, JSON table, B-tree, SELECT, PRAGMA, trigger/FK, or
  source-neutral cleanup surfaces. The new surface is specifically successful
  retry WAL checksum materialization after a rollback-preserved prefix.

Dependency closure:

- No new support component is needed. This reuses existing native WAL checksum,
  JSON import savepoint, and app-WAL rollback primitives.
