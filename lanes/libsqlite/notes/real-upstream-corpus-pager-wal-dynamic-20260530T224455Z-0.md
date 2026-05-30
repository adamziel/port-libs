# real-upstream-corpus-pager-wal-dynamic-20260530T224455Z-0

Implemented real upstream pager/WAL protocol coverage from:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/walprotocol.test`
  - `walprotocol-1.1`: recovery lock sequence
  - `walprotocol-1.3`: recovery lock busy retries to locking protocol error
  - `walprotocol-1.4`: writer lock busy retries to locking protocol error
  - `walprotocol-1.5`: readmark lock busy path still reads successfully
  - `walprotocol-2.5` / `2.6`: reader during recovery unlock sees full rowset
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/walprotocol2.test`
  - `walprotocol2-2.2` / `2.3`: `BEGIN EXCLUSIVE` races with a concurrent writer and returns busy
  - `walprotocol2-2.4` / `2.5`: busy-handler retry succeeds after the snapshot race

Changes:

- Extended `SQLiteRealUpstreamPagerWalDynamicPlan` with `walProtocolRetrySnapshotCases()`.
- Added `SQLiteRealUpstreamPagerWalProtocolDynamicTest.php` with 1000 distinct dynamic TestRunner cases plus one summary case.

Focused evidence:

- `php -l lanes/libsqlite/src/SQLiteRealUpstreamPagerWalDynamicPlan.php`: no syntax errors.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalProtocolDynamicTest.php`: no syntax errors.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalProtocolDynamicTest.php`: `1 test files, 20579 assertions, 0 failures`, `1001` PASS lines.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`: `1 test files, 3 assertions, 0 failures`.
- `git diff --check -- lanes/libsqlite`: passed.
- Added-lines source-neutral scan over changed source/test/note files: no WordPress/domain-specific matches.

Status delta:

- `lane-status.json` `phpPass`: `991889 -> 992890`.
- Mapped denominator coverage remains `1589 / 1589`.

Non-overlap:

- This ports `walprotocol.test` and `walprotocol2.test` locking-protocol/busy-snapshot retry behavior.
- It does not repeat accepted WAL byte truncation, rollback-journal apply/commit, super-journal commit, VFS sync/apply/locked writer, `wal2-14` fullsync, `wal2-1..6` recovery/locking matrix, NOOP checkpoint, WAL persist, WAL overwrite, JSON table, B-tree, or SELECT corpus slices.

Dependency closure:

- No new support component is needed. The slice reuses the existing real upstream pager/WAL dynamic plan and models the upstream VFS lock callback outcomes as lane-local structured rows.
