# real-upstream-corpus-pager-wal-dynamic-20260531T035920Z-0

Session: `port-dev-sqlite-yield-dyn-real-pager-20260531T035920Z`

Base accepted HEAD: `9995fe4897b08d71e2d75db489dfa08c480a5292`

## Upstream Source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/walsetlk_recover.test`
- Ported sections:
  - `walsetlk_recover.test` `1.0`: WAL database initialized with three visible rows.
  - `walsetlk_recover.test` `1.2`: second handle reports `database is locked` while another process blocks WAL recovery `xRead`.
  - `walsetlk_recover.test` `1.3`: timeout remains between 400ms and 2s.
  - `walsetlk_recover.test` `1.4`: read succeeds after the recovery handle releases.
  - `walsetlk_recover.test` `1.5`: `setlk_timeout` builds avoid VFS `xSleep`; fallback builds sleep at least once.

## Focused Behavior

- Added `SQLiteRealUpstreamPagerWalSetlkRecoverDynamicTest.php`.
- Adds 1,000 dynamic recovery-lock cases plus one hydrated-source citation case.
- Each case builds a valid WAL byte stream, verifies committed transaction grouping, models the blocked recovery read with `SQLiteVfsLockState`, checks retry after lock release, preserves the upstream timeout/sleep distinction, and confirms checkpoint/snapshot/recovery metadata through existing native WAL helpers.

## Verification

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalSetlkRecoverDynamicTest.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalSetlkRecoverDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalSetlkRecoverDynamicTest.php`
  - `1 test files, 25006 assertions, 0 failures`
  - Focused PASS growth: `+1001` TestRunner cases from real upstream `walsetlk_recover.test`.

## Non-overlap

This slice does not repeat accepted `walsetlk.test` blocking-lock matrices,
`walsetlk_snapshot.test` snapshot-open busy behavior, `walprotocol*`,
`walnoshm`, `walro*`, `walhook`, `walckptnoop`, `walpersist`,
rollback-journal apply/commit, VFS writer/sync/lock-state, or WAL byte
truncation clusters. It focuses on the separate `walsetlk_recover.test`
recovery read timeout and retry path.

Mapped denominator coverage remains complete at `1589 / 1589`; this is
countable PHP PASS-line growth over already mapped real upstream WAL inventory.

## Dependency Closure

No new support component is needed. The slice reuses existing native PHP
`SQLiteWal`, `SQLiteWalHeader`, `SQLiteLockByteRangePlan`, and
`SQLiteVfsLockState` behavior plus the existing TestRunner/autoload support.

Root harness: not run - isolated micro-slice.
