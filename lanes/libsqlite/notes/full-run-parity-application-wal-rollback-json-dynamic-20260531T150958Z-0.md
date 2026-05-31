# full-run-parity-application-wal-rollback-json-dynamic-20260531T150958Z-0

Base accepted HEAD: `4678f572bda3b3437f0480f42476c787d671be75`.

Implemented a generic application WAL post-checkpoint follow-up parity extension for the existing rollback-disabled JSON recovery chain. The new scenarios start after:

- a rollback-disabled partial JSON batch materializes committed WAL prefix frames,
- a later JSON tail failure rolls back only its current tail,
- a corrected post-recovery JSON import commits two replacement WAL frames,
- a released restart/truncate checkpoint materializes those frames into the database image and resets the WAL generation.

The new `dynamicPostCheckpointFollowupScenarios*()` coverage then proves a fresh two-frame JSON import can start from the checkpointed database image, with an empty post-checkpoint WAL generation. Restart mode reuses the released checkpoint WAL header, truncate mode rebuilds a header from the next checkpoint salt, both modes chain frame checksums from the reset header, and the final follow-up insert is retained without reviving either prior rolled-back tail insert.

Non-overlap:

- Does not repeat the earlier rollback-disabled partial, follow-up, post-recovery recovery, or post-recovery checkpoint restart/truncate assertions.
- Does not repeat WAL byte-truncation, rollback-journal application, checkpoint transaction, VFS writer, or source-neutral cleanup slices.
- Reuses existing generic `app_settings` row terms and WAL/checkpoint/materialization helpers.

Focused assertion movement:

- Before this slice: `php -d memory_limit=1024M tools/run-tests.php lanes/libsqlite/tests/SQLiteApplicationWalRollbackJsonDynamicParityTest.php` reported `1 test files, 11106 assertions, 0 failures`.
- After this slice: same command reports `1 test files, 11819 assertions, 0 failures`.
- Focused delta: `+713` assertions in this file.

Verification:

- `php -l lanes/libsqlite/src/SQLiteJsonImportRollbackWalPlan.php` -> no syntax errors.
- `php -l lanes/libsqlite/tests/SQLiteApplicationWalRollbackJsonDynamicParityTest.php` -> no syntax errors.
- `php -l lanes/libsqlite/examples/application-wal-rollback-json-dynamic-parity.php` -> no syntax errors.
- `php lanes/libsqlite/examples/application-wal-rollback-json-dynamic-parity.php --self-test` -> `application-wal-rollback-json-dynamic-parity self-test passed`.
- `php -d memory_limit=1024M tools/run-tests.php lanes/libsqlite/tests/SQLiteApplicationWalRollbackJsonDynamicParityTest.php` -> `1 test files, 11819 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php` -> `1 test files, 3 assertions, 0 failures`.
- `php -r '$json=json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true); if (!is_array($json)) { fwrite(STDERR, json_last_error_msg().PHP_EOL); exit(1); } echo "lane-status.json valid\n";'` -> `lane-status.json valid`.
- `git diff --check -- lanes/libsqlite` -> passed with no output.

Root harness:

- Not run - isolated micro-slice.

Dependency closure:

- No new support component is needed.
- Existing bounded components reused: JSON import savepoint planning, WAL parsing/checkpoint planning, durable checkpoint sidecar shaping, and application WAL byte materialization.

Next useful follow-up:

- Apply the post-checkpoint state through broader pager/VFS transaction paths or continue default-memory app-WAL/pager pressure work, rather than adding another standalone restart/truncate variant.
