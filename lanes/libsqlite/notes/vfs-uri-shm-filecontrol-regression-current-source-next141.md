# VFS URI SHM File-Control Regression Current Source Next141

## Behavior

- Adds `SQLiteVfsLockByteUriShmCurrentSourceNext::currentSourceNext141()`.
- Fixes the SHM close/unmap regression path: an explicit SHM close carrying a connection releases that connection's SHM locks before later checkpoint/import handles reopen the sidecar.
- Keeps close-without-connection behavior unchanged, so existing lock state remains visible until a connection yield or explicit close connection is observed.
- Preserves URI file-control reads, database-routed write file-controls, data-version staleness, and refresh behavior on the reopened SHM current source.

## Focused Evidence

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteVfsUriShmFileControlRegressionCurrentSourceNext141Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 52 assertions, 0 failures
```

Regression evidence:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteVfsShmLockByteFileControlCurrentSourceNext112Test.php lanes/libsqlite/tests/SQLiteVfsShmLockByteUriFileControlCurrentSourceNext117Test.php lanes/libsqlite/tests/SQLiteVfsOpenShmFileControlUriCurrentSourceNext128Test.php
Focused test run: 3 selected test files (root lock skipped)
...
3 test files, 179 assertions, 0 failures
```

## Application Smoke

```text
php lanes/libsqlite/examples/application-vfs-uri-shm-filecontrol-regression-current-source-next141.php --self-test
application-vfs-uri-shm-filecontrol-regression-current-source-next141 self-test passed
```

Dashboard delta: `phpPass` moves from `61676` to `61728` from 52 new focused PASS lines if accepted. Mapped upstream coverage remains `606 / 1589`; this is a focused PHP regression over already mapped VFS URI/SHM/file-control primitives rather than a fresh manifest-backed upstream row.

## Non-Overlap

This avoids the queued next132/next133/next134 temp/URI planner rebases and accepted VFS lock-state, process-lock, file-writer, locked-writer, sync/apply, rollback-journal, WAL checkpoint/savepoint, URI SHM file-control next112/117/128, and lock-byte range clusters. The new surface is specifically SHM close/unmap releasing connection-scoped SHM locks on the current-source URI/file-control regression path.

## Dependency Closure

No new support component is needed. The patch reuses the existing lane-local SQLite URI parser, lock-byte planner, and SHM current-source state model.
