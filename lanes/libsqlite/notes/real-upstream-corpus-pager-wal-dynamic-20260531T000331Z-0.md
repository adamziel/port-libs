# real-upstream-corpus-pager-wal-dynamic-20260531T000331Z-0

Base accepted HEAD: `dd1b1090c602dc6e35c0593d57edce4faedf25d2`.

Upstream sources:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/walmode.test`
  - `walmode-0.1` through `0.3`: WAL unsupported falls back to rollback mode.
  - `walmode-1.1` through `1.7`: file-backed WAL activation, file versions, sidecar creation/removal.
  - `walmode-3.1` through `3.2`: already-WAL file opens the log without rewriting the database.
  - `walmode-4.1` through `4.18`: WAL to rollback transitions and peer-reader/open-connection lock failures.
  - `walmode-5.1` through `5.3`: memory/temp databases reject WAL.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/pager1.test`
  - `pager1-1.*`: multi-client locking, RESERVED/PENDING behavior, stale-reader visibility, lock errors, and commit visibility.

Patch:

- Added `SQLitePagerWalDynamicPlan` for generic pager/WAL journal-mode transitions and pager multiclient lock states.
- Added `SQLiteRealUpstreamPagerWalModeLockDynamicCorpusTest` with 1204 focused assertions.

Verification:

- `php -l lanes/libsqlite/src/SQLitePagerWalDynamicPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalModeLockDynamicCorpusTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalModeLockDynamicCorpusTest.php`
  - `1 test files, 1204 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - `1 test files, 3 assertions, 0 failures`
- `git diff --check -- lanes/libsqlite`

Delta:

- Focused PHP assertion growth: `+1204`.
- Expected selected PASS movement: `1273283 -> 1274487`.
- Mapped denominator: unchanged at `1589 / 1589`.

Non-overlap:

- Avoids accepted WAL persist limit, WAL checkpoint transaction, VFS rollback/apply/sync/lock/file-writer, pager cache-spill, hot-journal, and savepoint byte-truncation clusters.
- This slice covers upstream `walmode.test` journal-mode transition behavior and `pager1.test` multi-client lock progression only.

Dependency closure:

- No new support component is needed. The slice reuses existing generic PHP test runner and lane autoloading.
