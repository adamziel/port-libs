# real-upstream-corpus-pager-wal-dynamic-20260531T042727Z-0

Added `SQLiteRealUpstreamPagerWalDynamic20260531T042727ZTest.php` as an
additive real upstream pager/WAL corpus batch.

Hydrated upstream source files cited:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/pager2.test`
  - `pager2-1.*`: rollback preserves the pre-transaction database image.
  - `pager2-2.1`: `journal_mode=off` rollback leaves the changed image.
  - `pager2-2.2`: auto-vacuum shrink with `journal_mode=off` keeps the
    truncated image.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/wal8.test`
  - `1.0`: PASSIVE checkpoint keeps WAL bytes for an active reader.
  - `2.0`: RESTART checkpoint waits for readers before reset.
  - `3.0`: TRUNCATE checkpoint clears reusable WAL bytes when eligible.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/wal9.test`
  - `1.1` through `1.7`: large WAL checkpoint/readback preserves
    reader-visible rows after WAL and SHM growth.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/walshared.test`
  - `walshared-1.*`: shared-cache WAL readers observe committed state.

Focused movement:

- 1001 distinct TestRunner PASS cases.
- 31001 behavior assertions.
- No mapped denominator movement claimed.
- No production source changes and no new support component required; the
  batch reuses existing `SQLiteWal` and `SQLiteWalHeader` primitives.

Non-overlap:

This avoids accepted pager/WAL mode-persist, noop-checkpoint, WAL checksum,
overwrite/restart/crash recovery, readonly-SHM, hook/protocol, setlk/snapshot,
real-pager boundary, WAL byte truncation, VFS writer/sync/lock/rollback,
rollback-journal commit, super-journal, checkpoint transaction, app-WAL, and
pager master-journal numbered surfaces. The new surface is the upstream
`pager2.test` rollback/journal-off plus `wal8.test`, `wal9.test`, and
`walshared.test` checkpoint/readback/shared-cache behavior.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalDynamic20260531T042727ZTest.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalDynamic20260531T042727ZTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalDynamic20260531T042727ZTest.php`
  - `1 test files, 31001 assertions, 0 failures`

Dependency closure:

No new support component is needed. Existing WAL parsing, transaction recovery
boundary, reader snapshot, checkpoint mode, and durable checkpoint primitives
cover this upstream corpus batch.
