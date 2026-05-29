# WAL hot-journal savepoint checkpoint current-source next187

Slice: `wal-hot-journal-savepoint-checkpoint-current-source-next187`

Behavior added:

- Adds `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan`, a bounded planner for the handoff between accepted next183 post-apply current-source verification and accepted next184 reopened retry WAL source admission.
- The new behavior retires the post-apply reader token before admitting a retry WAL checkpoint source, so a WordPress import retry cannot reuse a reader mark pinned to the hot-journal-recovered source.
- The plan records token classifications, stale/retained reader tokens, retry admission state, and non-overlap/dependency closure evidence.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext187Test.php`
- `php lanes/libsqlite/examples/wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next187.php --self-test`
- `php -l lanes/libsqlite/src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext187Test.php`
- `php -l lanes/libsqlite/examples/wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next187.php`
- `git diff --check -- lanes/libsqlite`

Non-overlap:

- This does not repeat next183 current-source verification, next184 WAL salt/checkpoint parsing, atomic file-map apply, WAL byte truncation, rollback-journal apply, checkpoint transactions, VFS writer/sync application, or accepted batch171 WAL reader-separation behavior.

Dependency closure:

- No new support component is needed. The slice composes accepted lane-local post-apply current-source and reopened WAL source evidence.
