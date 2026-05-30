# Real Upstream Corpus Pager WAL Dynamic 20260530T211934Z-0

Base accepted HEAD: `79fe7adeaeaffcf972bbb3cc5bff694c367cc63d`.

Added `SQLiteRealUpstreamPagerWal2SnapshotDynamicCorpusTest.php` with 1,000
generated-but-behavioral WAL byte-stream cases plus one upstream provenance
case. The cases cite hydrated upstream SQLite files:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/wal2.test`
  - `wal2-1.*` wal-index header race recovery while a reader snapshots a valid
    prefix.
  - `wal2-2.*` stale wal-index header replacement and committed-prefix
    recovery.
  - `wal2-6.*` read-mark / `mxFrame` interactions with pinned reader
    snapshots.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/walpersist.test`
  - `walpersist-1.*` persistent WAL sidecar decisions across close/reopen.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/walnoshm.test`
  - heap wal-index reader snapshots without a persistent `-shm` file.

Focused behavior covered:

- WAL transaction recovery keeps the latest committed prefix and discards an
  uncommitted writer tail.
- Reader snapshots honor pinned end-frame choices against committed WAL
  frames.
- Checkpoint visibility, checkpoint mode output, durable checkpoint byte
  lengths, and checkpointed database images stay coherent across page sizes,
  little/big-endian WAL checksums, checkpoint modes, and reader pins.

Focused verification:

```text
php -l lanes/libsqlite/tests/SQLiteRealUpstreamPagerWal2SnapshotDynamicCorpusTest.php
No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamPagerWal2SnapshotDynamicCorpusTest.php

php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPagerWal2SnapshotDynamicCorpusTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 20001 assertions, 0 failures
```

Expected dashboard movement: `+1001` focused TestRunner PASS lines and
`+20001` behavior assertions. Mapped denominator coverage remains `1589 / 1589`
because this is additional real upstream behavior coverage over already mapped
WAL/pager inventory, not a new upstream script id.

Dependency closure: no new support component is needed; the slice reuses the
existing native `SQLiteWal` parser, checksum, snapshot, recovery-boundary, and
checkpoint helpers.

Non-overlap: avoids the already accepted pager/WAL dynamic matrix,
restart/noop, persist-mode, overwrite, crash recovery, setlk blocking,
checkpoint-sync, and rollback-journal application clusters by targeting
`wal2.test` wal-index/read-mark snapshot recovery plus `walpersist.test` and
`walnoshm.test` sidecar/no-shm reader behavior.
