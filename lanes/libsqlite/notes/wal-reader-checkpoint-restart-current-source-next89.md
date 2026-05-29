# wal-reader-checkpoint-restart-current-source-next89

Status: focused PHP behavior growth for WAL reader checkpoint restart current-source validation.

This slice adds `SQLiteWal::checkpointReaderRestartCurrentSourceNext()` for the WAL checkpoint restart boundary where a current SHM reader pins an older snapshot, another reader pins the latest commit, and the final retry can restart or truncate the WAL only after all read marks release. The new wrapper validates the raw current WAL sidecar bytes against the parsed WAL source before trusting SHM restart state, then reports current/next/final source provenance for copied WordPress database diagnostics.

Focused evidence:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalReaderCheckpointRestartCurrentSourceNext89Test.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 73 assertions, 0 failures
```

Additional verification:

```text
php -l lanes/libsqlite/src/SQLiteWal.php
php -l lanes/libsqlite/tests/SQLiteWalReaderCheckpointRestartCurrentSourceNext89Test.php
php -l lanes/libsqlite/examples/wordpress-wal-reader-checkpoint-restart-current-source-next89.php
php lanes/libsqlite/examples/wordpress-wal-reader-checkpoint-restart-current-source-next89.php --self-test
git diff --check -- lanes/libsqlite
```

PASS delta: `+73` focused PASS lines. `lane-status.json` `phpPass` moves from `34719` to `34792`. Mapped upstream coverage is unchanged because this composes already mapped WAL restart, read-mark, checkpoint, and current-source primitives.

Non-overlap: this avoids accepted WAL reader-pin restart/truncate handoff, WAL savepoint byte truncation, WAL checkpoint/savepoint current-source next87, VFS savepoint rollback, rollback-journal commit/apply, super-journal commits, VFS writer/sync/lock clusters, B-tree page move/root collapse/overflow release, JSON table cursor/source/constraint work, SELECT SQL text/subquery/group/order clusters, and Unicode GLOB. The new behavior is raw WAL sidecar current-source validation plus source provenance at the reader checkpoint restart boundary.

Dependency closure: no new support component is needed. The slice reuses lane-local WAL parsing/checksum validation, SHM read-mark parsing, durable checkpoint result planning, and reader snapshot helpers.

Next task: apply the same current-source validation discipline to broader pager/VFS transaction application only if it can add focused behavior coverage without repeating the accepted writer/sync/savepoint clusters.
