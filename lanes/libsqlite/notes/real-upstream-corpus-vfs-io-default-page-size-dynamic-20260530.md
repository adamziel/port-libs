# Real Upstream Corpus VFS I/O Default Page Size Dynamic

- Session: `port-dev-sqlite-yield-dyn-real-vfs-20260530T183258Z`
- Micro-slice: `real-upstream-corpus-vfs-io-dynamic-20260530T183258Z-0`
- Accepted base: `2b09fd94bbc734a3a9855d41884522c7a5a06914`
- Upstream source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/io.test`
- Upstream scenarios cited: `io-5.1` through `io-5.11`, plus the same `sqlite3_simulate_device -char/-sectorsize` default-page-size behavior exercised across sync and dirty-page transaction variants.

## Delta

Added `SQLiteRealUpstreamCorpusVfsIoDefaultPageSizeDynamicTest.php`.

The test ports the `io.test` `io-5.*` device-characteristic/default-page-size matrix into a broad dynamic PHP corpus against `SQLiteVfsIoTrafficPlan::transaction()`. It verifies:

- default page size selection for no device flags, `atomic`, `atomic512`, `atomic2K`, `atomic64K`, and combined atomic flags;
- sector-size preservation and requested page-size preservation;
- sync-count behavior for `off`, `normal`, and `full` modes;
- database write accounting for single-page, two-page, append-page, and read-only shapes;
- canonical `io-5.1` through `io-5.11` expectations from the hydrated upstream file;
- guard behavior for invalid page and sector sizes.

Focused result:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsIoDefaultPageSizeDynamicTest.php
1 test files, 40388 assertions, 0 failures
```

PASS-line growth: `+5053` focused TestRunner PASS cases.

## Non-Overlap

This does not repeat accepted VFS atomic batch fallback, `io.test` transaction sequence `io-2.*`/`io-3.*`/`io-4.*`, pager/WAL dynamic corpus, VFS atomic journal admission, rollback-journal commit/apply, VFS sync/apply, lock-state, locked-writer, or file-control persistence surfaces. The owned behavior is the real upstream `io-5.*` default page-size selection matrix.

## Dependency Closure

No new support component is needed. The slice reuses the existing bounded `SQLiteVfsIoTrafficPlan` and hydrated upstream `io.test` behavior.
