# VFS Lock Byte-Range Current/Next 72

Slice: `vfs-locking-byte-range-current-next72`

Behavior added:

- Adds `SQLiteLockByteRangePlan::transition()` for current-to-next SQLite VFS byte-range lock planning.
- Reports current ranges, next ranges, retained ranges, newly acquired ranges, and released ranges for `none`, `shared`, `reserved`, `pending`, and `exclusive` transitions.
- Covers shared-slot movement, reserved-to-exclusive promotion, exclusive-to-pending demotion, release-to-none, and `nolock` blocked acquisition versus allowed release.

Focused verification:

```text
$ php tools/run-tests.php lanes/libsqlite/tests/SQLiteVfsLockByteRangeCurrentNext72Test.php
Focused test run: 1 selected test files (root lock skipped)
PASS vfs lock byte range current next72 none to shared acquires selected shared slot
PASS vfs lock byte range current next72 shared to reserved retains shared slot and acquires reserved byte
PASS vfs lock byte range current next72 reserved to pending releases shared and retains reserved
PASS vfs lock byte range current next72 pending to exclusive retains pending and reserved and acquires shared range
PASS vfs lock byte range current next72 exclusive to none releases all lock bytes
PASS vfs lock byte range current next72 shared slot move releases old reader slot and acquires new slot
PASS vfs lock byte range current next72 reserved slot move keeps reserved but swaps reader slot
PASS vfs lock byte range current next72 reserved to exclusive keeps reserved and acquires pending plus shared range
PASS vfs lock byte range current next72 exclusive to pending keeps pending and reserved and releases shared range
PASS vfs lock byte range current next72 pending to reserved keeps reserved and releases pending
PASS vfs lock byte range current next72 preserves reserved byte while promoting to exclusive
PASS vfs lock byte range current next72 release plan keeps connection optional for none
PASS vfs lock byte range current next72 nolock blocks acquisition but not release
PASS vfs lock byte range current next72 validates next writer connection

1 test files, 150 assertions, 0 failures
```

Expected status movement:

- `phpPass`: `26631 -> 26645` (`+14` verified PASS lines).
- `benchmarkDenominator.mapped`: unchanged; this is focused PHP behavior coverage, not a new upstream inventory unit.

Application smoke:

- `php lanes/libsqlite/examples/application-vfs-lock-byte-range-current-next72.php`
- Demonstrates copied Application database reader/writer byte-range current/next lock transitions before native VFS application without requiring `ext/sqlite`.

Dependency closure:

- No new support component is needed.
- Reuses existing bounded VFS/open locking primitives and adds transition planning needed by native VFS lock application.

Non-overlap:

- Does not repeat accepted VFS lock byte-range constants/basic plans, VFS lock-state application, process file locks, locked writers, file-control state, WAL checkpoint transaction, or rollback/sync writer application.
