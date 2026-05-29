# WAL/VFS numbered method consolidation sixty-sixth pass

Consolidated the VFS current-source production dispatch helper names in
`SQLiteVfsCurrentSourceNextPlan.php` for the mmap/shared-memory,
environment/access, time/syscall, path/control, access/delete/random/sleep,
and sync/truncate/reserve windows. The canonical private helpers now use stable
descriptive names:

- `runMmapSharedMemory()`
- `runEnvironmentAccess()`
- `runTimeErrorSyscall()`
- `runPathControlNames()`
- `runAccessDeleteRandomSleep()`
- `runSyncTruncateSizeReserve()`

No compatibility shim was left for the removed numbered private method names.
The existing public slice labels, tests, and examples continue to select the
same behavior through the canonical consolidated VFS class.

Verification:

- `php -l lanes/libsqlite/src/SQLiteVfsCurrentSourceNextPlan.php` passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteVfsCurrentSourceNext158161Test.php lanes/libsqlite/tests/SQLiteVfsCurrentSourceNext162165Test.php lanes/libsqlite/tests/SQLiteVfsCurrentSourceNext166169Test.php lanes/libsqlite/tests/SQLiteVfsCurrentSourceNext170173Test.php lanes/libsqlite/tests/SQLiteVfsCurrentSourceNext174177Test.php lanes/libsqlite/tests/SQLiteVfsCurrentSourceNext178181Test.php` passed with `6 test files, 120 assertions, 0 failures`.

Dependency closure: no new support component is needed. This is a production
helper-name consolidation over existing lane-local VFS current-source behavior.

Non-overlap: this pass only removes numbered private VFS dispatch helper names.
It does not change WAL/pager durability behavior, B-tree, JSON table, planner,
or upstream-suite evidence behavior, and it keeps the user-named `150` suffix
out of production methods.
