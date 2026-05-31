# full-run-parity-application-wal-rollback-json-dynamic-20260531T153340Z-0

## Scope

Adds generic application-WAL post-checkpoint tail rollback/recovery parity on top of the accepted post-checkpoint follow-up coverage.

The new source path starts from `dynamicPostCheckpointFollowupScenarios()`, appends a later malformed JSON tail batch, and verifies that rollback truncates back to the committed two-frame follow-up prefix. A corrected recovery batch then commits after that rollback, retains the prior follow-up insert, and does not revive the failed tail insert.

This is non-overlapping with the accepted post-checkpoint follow-up, rollback-disabled follow-up/recovery, WAL byte truncation, JSON table source/cursor/visible-constraint, row-value/window, e_walhook, lock4, and window2 slices. The new synthetic insert pages stay within the existing post-checkpoint database image range to avoid the default-memory app-WAL pressure that remains a broader closure item.

## Evidence

Before this patch, the existing app-WAL dynamic parity file remained at:

- `php -d memory_limit=1024M tools/run-tests.php lanes/libsqlite/tests/SQLiteApplicationWalRollbackJsonDynamicParityTest.php`
- Result before edits: `1 test files, 12819 assertions, 0 failures`

After this patch:

- `php -l lanes/libsqlite/src/SQLiteJsonImportRollbackWalPlan.php`
- Result: `No syntax errors detected`
- `php -l lanes/libsqlite/examples/application-wal-rollback-json-dynamic-parity.php`
- Result: `No syntax errors detected`
- `php -l lanes/libsqlite/tests/SQLiteApplicationWalRollbackJsonPostCheckpointTailDynamicTest.php`
- Result: `No syntax errors detected`
- `php -d memory_limit=1024M tools/run-tests.php lanes/libsqlite/tests/SQLiteApplicationWalRollbackJsonPostCheckpointTailDynamicTest.php`
- Result: `1 test files, 1606 assertions, 0 failures`
- `php -d memory_limit=1024M tools/run-tests.php lanes/libsqlite/tests/SQLiteApplicationWalRollbackJsonDynamicParityTest.php`
- Result: `1 test files, 12819 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-wal-rollback-json-dynamic-parity.php --self-test`
- Result: `application-wal-rollback-json-dynamic-parity self-test passed`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
- Result: `1 test files, 3 assertions, 0 failures`
- `php -r 'json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'`
- Result: `lane-status json ok`
- `git diff --check -- lanes/libsqlite`
- Result: no output, exit 0

Focused assertion delta: `+1606` new assertions from `SQLiteApplicationWalRollbackJsonPostCheckpointTailDynamicTest.php`.

Lane status delta: `phpPass` moves from `3020960` to `3022566`; mapped coverage remains `1589 / 1589`.

## Dependency Closure

No new support component is needed. The slice reuses the existing JSON mutation, savepoint statement-journal, WAL checksum/materialization, checkpoint reset, and rollback-to-savepoint primitives.

## Next

The next non-overlapping app-WAL work should reduce default-memory app-WAL/pager pressure or apply this post-checkpoint state through broader pager/VFS transaction paths. Do not add another restart/truncate metadata variant for this same tail sequence.
