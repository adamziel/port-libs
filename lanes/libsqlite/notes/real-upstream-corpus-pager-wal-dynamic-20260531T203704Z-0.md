# real-upstream-corpus-pager-wal-dynamic-20260531T203704Z-0

Session: `port-dev-sqlite-yield-dyn-real-pager-20260531T203704Z`

Base accepted HEAD: `29362e0d6ada0a9ddb4cefdc699cee6add41d488`

Source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/walslow.test`
- Ported sections: `walslow.test` `3.1` through `3.3`.

Behavior covered:

- Adds `SQLiteRealUpstreamCorpusPagerWalSlowDynamic20260531T203704ZTest.php`.
- Ports the upstream single-byte WAL corruption loop where a database starts
  with rows `1 2`, one WAL frame appends row `3`, then each one-byte
  corruption in frame header/checksum/page-image data makes the WAL frame
  unusable so readers stay on the pre-WAL database image.
- Covers 1000 deterministic offset/increment cases over the 1024-byte
  `walslow.test` frame area, including commit-word, salt, checksum, and page
  image byte classes.
- Asserts both native PHP recovery layers: `checksumRecoveryBoundary()` keeps
  the exact low-level salt/checksum mismatch reason, while
  `transactionRecoveryBoundary()` exposes the committed-prefix recovery result
  with zero valid/committed WAL frames and no checkpointable image.

Focused evidence:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPagerWalSlowDynamic20260531T203704ZTest.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPagerWalSlowDynamic20260531T203704ZTest.php`
- `php -d memory_limit=1024M tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPagerWalSlowDynamic20260531T203704ZTest.php`
  - `1 test files, 27014 assertions, 0 failures`
  - Focused PASS growth: `+1002` TestRunner cases.

Expected status delta:

- `phpPass`: `3847998 -> 3849000`
- `benchmarkDenominator.mapped`: unchanged at `1589 / 1589`; the upstream
  denominator is already fully mapped.

Non-overlap:

- This slice owns `walslow.test` section `3.1` through `3.3` only.
- It avoids accepted `walcksum` committed-prefix recovery, `walcrash`
  tail-recovery batches, `walrestart`, `walpersist`, `walsetlk`, `walro`,
  `walnoshm`, WAL byte truncation, checkpoint transaction plan,
  rollback-journal apply/commit, VFS writer/sync/lock, app-WAL, JSON, SELECT,
  B-tree, trigger, PRAGMA, and encoding clusters.

Dependency closure:

- No new support component is needed. The slice reuses native PHP
  `SQLiteWal` checksum recovery, transaction recovery, and reader snapshot
  primitives with hydrated upstream `walslow.test` source evidence.

Next task:

- Continue pager/WAL real-corpus work only on a non-overlapping upstream
  section or fix a named release/all-runner blocker that unlocks broader
  default-memory pager/WAL evidence.
