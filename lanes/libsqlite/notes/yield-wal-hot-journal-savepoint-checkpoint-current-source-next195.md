# WAL Hot-Journal Savepoint Checkpoint Current Source Next195

## Behavior

Adds `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan`, a bounded
reader retry admission fence for the WAL/hot-journal/savepoint/checkpoint path.
After hot-journal recovery, failed-savepoint rollback, and checkpoint current
source publication, a retry reader is admitted only when all observed source
metadata still matches:

- current source token and epoch;
- checkpoint cookie and schema cookie;
- WAL salt;
- hot-journal generation;
- savepoint generation;
- hot journal removed and checkpoint published;
- reader is not dirty or closed.

Stale retry handles are marked `requires_reopen` with precise failed checks.

## Evidence

Focused verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext195Test.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 57 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/libsqlite/examples/wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next195.php
wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next195 self-test passed
```

Syntax:

```text
php -l lanes/libsqlite/src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php
php -l lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext195Test.php
php -l lanes/libsqlite/examples/wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next195.php
```

## Dashboard Delta

Expected libsqlite `phpPass`: `93683 -> 93740` (`+57`) on acceptance.
Mapped upstream coverage remains `618 / 1589`; this slice does not claim a new
manifest-backed upstream row.

## Non-Overlap

This slice does not repeat WAL byte truncation, VFS writer apply, post-apply
receipt next178, page-cache admission next191, or hot-journal checkpoint byte
publication. It covers reader retry source admission after those publication
steps.

## Dependency Closure

No new support component is needed. The slice reuses lane-local WAL checkpoint
tokens, hot-journal generation markers, and reader retry metadata.
