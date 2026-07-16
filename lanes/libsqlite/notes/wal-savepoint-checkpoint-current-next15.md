# WAL Savepoint Checkpoint Current Next15

Status: focused PHP corpus growth for WAL savepoint rollback current-prefix checkpoint behavior.

Behavior:

- Added `SQLiteWalSavepointCheckpointPlan::afterRollbackTo()` to compose existing savepoint WAL byte truncation with current WAL checkpoint planning/result generation.
- The planner checkpoints the retained WAL prefix after `ROLLBACK TO`, excludes discarded current/nested savepoint frames from the database image, and reports reset/truncate eligibility for PASSIVE/FULL/RESTART/TRUNCATE modes.
- Reader-pinned RESTART checkpoints remain busy against the retained prefix, matching SQLite's rule that current readers can block WAL reset even after savepoint rollback has discarded later frames.
- Added `SQLiteWalSavepointCheckpointCurrentNext15Test.php` with 46 independent PASS cases.
- Added `application-wal-savepoint-checkpoint-current.php` to smoke a copied `wp_options` plugin-settings import that rolls back a failed savepoint before a TRUNCATE checkpoint.

Verification:

- `php -l lanes/libsqlite/src/SQLiteWalSavepointCheckpointPlan.php`: no syntax errors.
- `php -l lanes/libsqlite/tests/SQLiteWalSavepointCheckpointCurrentNext15Test.php`: no syntax errors.
- `php -l lanes/libsqlite/examples/application-wal-savepoint-checkpoint-current.php`: no syntax errors.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalSavepointCheckpointCurrentNext15Test.php`: 1 test files, 46 assertions, 0 failures.
- `php lanes/libsqlite/examples/application-wal-savepoint-checkpoint-current.php --self-test`: passed; reported 2 retained frames, 2 discarded frames, `truncate_wal`, and no discarded plugin draft in the checkpoint database image.

Dashboard delta:

- `phpPass`: +46 from 4362 to 4408 in this isolated worktree.
- `benchmarkDenominator.mapped`: unchanged; this is focused native PHP corpus growth over existing WAL/savepoint/checkpoint inventory, not a newly mapped upstream unit.

Non-overlap:

- Avoids accepted savepoint counter preservation, page-image rollback, WAL byte truncation-only diagnostics, VFS savepoint rollback application, WAL checkpoint transaction planning, VFS writer/sync/lock clusters, rollback-journal commit/apply, and the current SQL/JSON/B-tree/encoding corpus.
- This slice specifically covers checkpoint planning/result semantics against the post-savepoint-rollback current WAL prefix.

Dependency closure:

- No new support component is needed. The slice reuses lane-local `SQLiteSavepointStack`, `SQLiteWal`, and checkpoint result helpers.

Next:

- Continue with broader pager/VFS transaction application, WAL durability, or a distinct release/all-suite blocker on current accepted HEAD.
