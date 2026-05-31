# real-upstream-corpus-pager-wal-dynamic-20260531T045007Z-0

Base accepted HEAD: `ea98db4ecded4356aee592549997cc44a35fab5b`

Added a focused real-upstream pager/WAL corpus batch:

- `wal5.test` `wal5-pragma 1.*` and `wal5-capi 1.*`: blocking restart checkpoint waits for a pinned reader before WAL wrap.
- `wal5.test` `wal5-pragma 2.1.*` and `wal5-capi 2.1.*`: checkpoint applies all attached WAL files.
- `wal5.test` `wal5-pragma 2.2.*` and `wal5-capi 2.2.*`: restart checkpoint reports busy with pinned main reader.
- `wal5.test` `wal5-pragma 2.3.*` and `wal5-capi 2.3.*`: full checkpoint backfills only unpinned frames.
- `wal5.test` `wal5-pragma 5.*` and `wal5-capi 5.*`: checkpoint modes preserve, restart, or truncate WAL with active readers.

The PHP batch exercises committed WAL-prefix recovery, durable checkpoint byte results, attached database checkpoint parity, reader-pinned checkpoint actions, and reader snapshot page-source boundaries using `SQLiteWal`.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalCheckpointBlockingDynamic20260531T045007ZTest.php`
- Result: `1 test files, 36001 assertions, 0 failures`
- PASS-line movement: `+1001`
- Expected selected count: `2125874 -> 2126875 pass / 0 fail`

Non-overlap:

- Does not repeat the accepted pager/WAL invalid-page-size, full-sync, lock-race, noop-checkpoint, overwrite, crash-recovery, warm-body, or prior 20260531T042727Z dynamic batches.
- Uses hydrated upstream `wal5.test` sections not already owned by the existing `SQLiteRealUpstreamPagerWalDynamic20260531T042727ZTest.php` or real-pager 20260530 dynamic files.

Dependency closure:

- No new support component is needed. This reuses the existing native PHP `SQLiteWal` parser, transaction recovery boundary, checkpoint planning, durable checkpoint result, and reader snapshot APIs.
