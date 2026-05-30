# real-upstream-corpus-vfs-io-dynamic appendvfs

Slice: `real-upstream-corpus-vfs-io-dynamic-20260530T205554Z-0`

Base accepted HEAD: `f32e8deaca85f9598bd0eb6230903f7d3fab9f57`

Upstream source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/avfs.test`

Ported behavior:

- `avfs.test` `avfs-1.0` through `avfs-1.4`: appendvfs stores the SQLite payload at offset 0 for empty appendees, aligns non-empty appendees to a 4096-byte boundary, preserves sorted/reopened rows, and records a `Start-Of-SQLite3-` trailer with the payload offset.
- `avfs.test` `avfs-2.1`: original appendee bytes remain preserved after appending the SQLite payload.
- `avfs.test` `avfs-3.1` through `avfs-3.5`: appended databases grow by many pages, shrink after delete/vacuum, and reopen with integrity checks still `ok`.
- `avfs.test` `avfs-4.1` through `avfs-4.3`: shell append-mode analogs preserve offset detection for non-empty and empty appendees and keep the appended database writable on reopen.
- `avfs.test` `avfs-5.1` and `avfs-5.2`: too-small appended SQLite payloads are rejected whether the declared offset is zero or follows a non-empty appendee.

Non-overlap:

- This does not repeat accepted `io.test`, `ioerr.test`, `ioerr2.test`, VFS checksum/WAL, lock-state, file-writer, rollback-journal apply, sync-apply, or VFS lock byte-range coverage.
- The new cluster is appendvfs-specific: offset trailer detection, appendee alignment, grow/shrink/reopen size behavior, and tiny appended database guards.
- Mapped denominator remains `1589 / 1589`; expected dashboard movement is PASS-line growth only.

Focused evidence:

- `php -l lanes/libsqlite/src/SQLiteAppendVfsPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamAppendVfsDynamicCorpusTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamAppendVfsDynamicCorpusTest.php`
- PASS count: `1906`
- Assertion count: `1 test files, 26287 assertions, 0 failures`

Dependency closure:

- No new support component is required. The slice reuses existing lane-local PHP test runner and class-loading infrastructure and adds bounded native PHP appendvfs planning under `lanes/libsqlite/src`.
- Next VFS I/O work should target a different upstream section such as `walvfs.test` or `e_walckpt.test` runtime blockers, or full release/all runner parity, not another appendvfs matrix.
