# full-run-parity-application-wal-rollback-json-dynamic-20260531T140120Z-0

Base accepted HEAD: `fcf80b184dffa3efc63a46882bb7f2080702858c`.

Implemented a generic application WAL post-recovery checkpoint parity extension for the existing rollback-disabled JSON recovery chain. The new scenarios start after:

- a rollback-disabled partial JSON batch materializes committed WAL prefix frames,
- a later JSON tail failure rolls back only its current tail,
- a corrected post-recovery JSON import commits two replacement WAL frames.

The new `dynamicPostRecoveryCheckpointScenarios*()` coverage then proves restart/truncate checkpoints materialize the corrected recovery WAL pages into the database image, supersede stale catalog frames, reset or truncate the WAL sidecar after all readers release, and preserve the original WAL bytes when a reader pins the frame before the final corrected recovery insert. Final row checks keep both rolled-back tail insert keys excluded while retaining the corrected recovery insert.

Non-overlap:

- Does not repeat the earlier rollback-disabled partial/followup/post-recovery recovery assertions.
- Does not add WordPress-specific API names or compatibility wrappers.
- Reuses existing generic `app_settings` row terms and WAL checkpoint/durable sidecar helpers.

Focused assertion movement:

- Before this slice: `php -d memory_limit=1024M tools/run-tests.php lanes/libsqlite/tests/SQLiteApplicationWalRollbackJsonDynamicParityTest.php` reported `1 test files, 10466 assertions, 0 failures`.
- After this slice: same command reports `1 test files, 11106 assertions, 0 failures`.
- Focused delta: `+640` assertions and `+241` focused PASS cases in this file.

Verification:

- `php -l lanes/libsqlite/src/SQLiteJsonImportRollbackWalPlan.php` -> no syntax errors.
- `php -l lanes/libsqlite/tests/SQLiteApplicationWalRollbackJsonDynamicParityTest.php` -> no syntax errors.
- `php -l lanes/libsqlite/examples/application-wal-rollback-json-dynamic-parity.php` -> no syntax errors.
- `php -d memory_limit=1024M tools/run-tests.php lanes/libsqlite/tests/SQLiteApplicationWalRollbackJsonDynamicParityTest.php` -> `1 test files, 11106 assertions, 0 failures`.
- `php lanes/libsqlite/examples/application-wal-rollback-json-dynamic-parity.php --self-test` -> `application-wal-rollback-json-dynamic-parity self-test passed`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoWordPressSpecificApiTest.php` -> not run because the guard file is absent in this worktree.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php` -> `1 test files, 3 assertions, 0 failures`.
- `php -r '$json=json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true); if (!is_array($json)) { fwrite(STDERR, json_last_error_msg().PHP_EOL); exit(1); } echo "lane-status.json valid\n";'` -> `lane-status.json valid`.
- `git diff --check -- lanes/libsqlite` -> passed with no output.

Supervisor integration verification on `a187757827b58c999a1fc6cda2f4be5e163b73e9`:

- `php -l` on changed PHP files -> passed.
- `php -d memory_limit=1024M tools/run-tests.php lanes/libsqlite/tests/SQLiteApplicationWalRollbackJsonDynamicParityTest.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php` -> `2 test files, 11109 assertions, 0 failures`.
- `php lanes/libsqlite/examples/application-wal-rollback-json-dynamic-parity.php --self-test` -> `application-wal-rollback-json-dynamic-parity self-test passed`.
- `git diff --check -- lanes/gitoxide lanes/libsqlite` -> passed.
- Full libsqlite lane was attempted, not accepted as green: at `1024M` it exhausted memory in the existing `SQLiteRealUpstreamCorpusPagerWalFaultDynamic20260531T081930ZTest.php`; that isolated file then passed at `2048M` with `1 test files, 59088 assertions, 0 failures`; a `2048M` full-lane rerun later failed on existing untouched `SQLiteRealUpstreamWindow2RowsFollowingDynamicTest.php` with `SQLite window frame specification is not supported`.

Dependency closure:

- No new support component is needed.
- Existing bounded components reused: JSON import savepoint planning, WAL parsing/checkpoint planning, durable checkpoint sidecar shaping, and application WAL byte materialization.

Next useful follow-up:

- Continue default-memory app-WAL/pager pressure work by applying these checkpoint results through broader pager/VFS transaction paths, rather than adding another standalone restart/truncate variant.
