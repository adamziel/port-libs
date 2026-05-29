# WAL Hot-Journal Savepoint Checkpoint Current-Source Next167

Date: 2026-05-28

This slice adds `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan`,
a current-source publication guard for the WAL/hot-journal/savepoint checkpoint
path. It composes the accepted next161/next164 reader-cache and reader-admission
plans, then verifies that the prepared current token, next-WAL token, exact WAL
bytes, hot-journal pages, savepoint before-images, and reader admission set still
match before checkpoint readers are admitted.

Focused verification:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext167Test.php
```

Result:

```text
Focused test run: 1 selected test files (root lock skipped)
1 test files, 64 assertions, 0 failures
```

WordPress smoke:

```sh
php lanes/libsqlite/examples/wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next167.php
```

Result: the copied WordPress plugin import scenario recovered hot-journal pages,
rolled back failed savepoint pages, verified the current-source publication
fingerprint, admitted only the matching current reader, and forced stale/next/dirty
readers to reopen.

Root harness status: not run - isolated micro-slice.

Non-overlap: this does not repeat accepted next164 reader admission, next161
cache-token rebasing, WAL byte truncation, checkpoint transaction planning,
VFS writer application, or hot rollback-journal apply paths. It adds the missing
publication guard that blocks stale mixed-source inputs before reader admission.

Dependency closure: no new support component is needed; the slice reuses
lane-local WAL parsing, hot-journal recovery planning, savepoint rollback
materialization, durable checkpoint token planning, and reader admission fences.
