# Real upstream corpus pager/WAL dynamic slice

Base accepted HEAD: `8bf0d9f81b29a5601901bb34dfd730670ed39bbc`.

This slice ports focused pager/WAL behavior from the hydrated upstream SQLite
corpus in `/home/claude/port-libs/.upstream-cache/libsqlite/test` into
lane-local PHP tests. It does not add metadata-only denominator rows.

Upstream source files and scenario names:

- `wal2.test`: `wal2-6.3` WAL-to-rollback checkpoint boundary and
  `wal2-7.1` reader-blocked restart reset.
- `wal7.test`: `wal7-3.0` zero journal-size-limit checkpoint truncation.
- `wal9.test`: `1.6-1.7` fully checkpointed WAL with stale reader mapping and
  rollback after a new write.
- `walnoshm.test`: `1.8-1.11` WAL sidecar removal when switching back to
  rollback mode.
- `walcksum.test`: checksum recovery over a valid WAL prefix.
- `walcrash.test`: committed prefix recovery when a WAL tail is corrupt.
- `walrestart.test`: reader-pinned checkpoint visibility.

Focused PHP coverage:

- Added `SQLiteRealUpstreamPagerWalDynamicCorpusTest.php`.
- 64 new TestRunner PASS cases.
- 768 focused assertions.
- Non-overlap: avoids current numbered pager/WAL helper families, suite
  denominator admission, and accepted rollback-journal/VFS writer/status
  surfaces. The tests exercise existing `SQLiteWal` parsing, checkpoint mode,
  durable checkpoint, reader visibility, transaction recovery, and corrupt
  recovery behavior.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalDynamicCorpusTest.php`
  passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalDynamicCorpusTest.php`
  passed: `1 test files, 768 assertions, 0 failures`.

Dependency closure:

- No new support component is needed. The slice reuses existing native
  `SQLiteWal`, `SQLiteWalHeader`, and TestRunner primitives.
