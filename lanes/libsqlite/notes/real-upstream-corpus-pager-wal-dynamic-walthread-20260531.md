## Real Upstream Corpus: Pager/WAL Walthread Dynamic

Slice: `real-upstream-corpus-pager-wal-dynamic-20260531T050712Z-0`

Base accepted HEAD: `7174979f2808c9ccf08c3331545660695c77e192`

Added focused PHP coverage for hydrated upstream SQLite `test/walthread.test`:

- `walthread-1`: mixed read/write transactions keep the checksum row invariant while checkpointing runs.
- `walthread-2`: rollback/WAL journal-mode switching keeps a single active sidecar family and integrity remains ok.
- `walthread-3`: ordered write bursts plus wal-hook checkpoint attempts preserve committed WAL prefix recovery.
- `walthread-4`: concurrent reader plus writer/checkpoint loop leaves readable snapshots.
- `walthread-5`: large WAL recovery stress remains deadlock-free and leaves no post-check error.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalThreadDynamicTest.php`
- Result: `1 test files, 22007 assertions, 0 failures`
- PASS-line growth: `1001`

Non-overlap:

- This adds `walthread.test` multi-client stress coverage. It avoids already accepted pager/WAL clusters for `walckptnoop`, `walprotocol`/`walnoshm`, `walsetlk*`, `walro2`, WAL byte truncation, WAL checkpoint transactions, VFS writer/sync/lock-state, rollback commit/apply, and existing `wal2` lock/checkpoint matrices.

Dependency closure:

- No new support component is needed. The coverage reuses generic WAL frame parsing, checkpoint mode planning, transaction recovery, reader snapshots, and lock coordination.
