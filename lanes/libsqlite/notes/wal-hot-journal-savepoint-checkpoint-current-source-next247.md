# WAL Hot Journal Savepoint Checkpoint Current Source Next247

## Behavior

Adds `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan`, a post-checkpoint cleanup admission guard for the WAL/hot-journal current-source chain.

The slice starts from an admitted `next243` reopened-reader baseline and seals cleanup only when receipts prove:

- hot-journal unlink;
- WAL sync;
- directory sync;
- savepoint release with depth `0`;
- reader fences for every admitted reopened reader;
- coverage for every dirty checkpoint page and committed WAL frame;
- matching current-source token, generation, schema cookie, database/page-cache digests, WAL-index salt, mx-frame, and checkpoint frame.

Blocked receipts keep the hot-journal cleanup fence in place and report the missing receipt kind, reader, dirty page, commit frame, duplicate receipt name, or stale current-source field.

## Focused Evidence

Command:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext247Test.php
```

Result:

```text
Focused test run: 1 selected test files (root lock skipped)
1 test files, 115 assertions, 0 failures
```

WordPress smoke:

```sh
php lanes/libsqlite/examples/wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next247.php --self-test
```

Expected:

```text
wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next247 self-test passed
```

## Non-Overlap

This does not repeat checkpoint publication, reader snapshot admission, WAL byte truncation, rollback-journal apply/commit, super-journal commits, VFS sync planning/application, process/file locks, SELECT, JSON, or B-tree surfaces. It is a narrower post-checkpoint cleanup receipt seal after accepted reader admission.

## Dependency Closure

No new support component is needed. The slice reuses lane-local PHP receipt arrays, next243 reopened-reader metadata, and existing digest/token/page/frame validation patterns.
