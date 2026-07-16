# Real Upstream Corpus VFS I/O Dynamic Pager Cache

Slice: `real-upstream-corpus-vfs-io-dynamic-20260530T212105Z-0`

Accepted base: `0c8f3edfb501039f3334d15acf03c96514063bb1`

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/io.test`
- Scenarios: `io-6.1` warm pager-cache setup and `io-6.2.1` / `io-6.2.2` post-commit corruption checks.

Behavior ported:

- Models the upstream `io.test` expectation that a warmed pager cache remains authoritative after atomic-device commits when `PRAGMA mmap_size = 0`.
- Covers the two upstream transaction shapes: a two-table commit and a one-table commit.
- Adds dynamic coverage for device-flag variants, cache-size/database-page boundaries, mmap-disabled preservation, mmap-enabled corruption visibility, and guard failures.

Focused verification:

- `php -l lanes/libsqlite/src/SQLiteVfsIoTransactionSequencePlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsIoPagerCacheDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsIoPagerCacheDynamicTest.php`
- Result: `1 test files, 19469 assertions, 0 failures`, with `1093` PASS lines.

Non-overlap:

- Does not repeat accepted VFS mmap-read, VFS file writer, VFS sync/apply, VFS lock-state/process-lock, rollback-journal apply/commit, WAL checkpoint transaction, or prior `io.test` `io-2` through `io-5` transaction/default-page coverage.
- This slice owns only `io.test` `io-6` warmed pager-cache behavior.

Dependency closure:

- No new support component is required. The slice reuses existing bounded VFS I/O transaction planning primitives and adds pager-cache outcome modeling in native PHP.
