# real-upstream-corpus-vfs-io-dynamic-20260531T005526Z-0

Slice: `real-upstream-corpus-vfs-io-dynamic-20260531T005526Z-0`

Upstream source truth:
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/avfs.test`
- Scenarios: `avfs-4.2` and `avfs-4.3`.

Behavior ported:
- Added `SQLiteAppendVfsPlan::updateExistingAppendDatabase()` for the appendvfs reopen-and-update path in `avfs-4.3`.
- The planner preserves the appendee prefix, keeps the appendvfs trailer offset stable, rewrites the trailer after growth, grows page count for appended rows, preserves sorted/reopened row visibility, and keeps integrity checks green.
- Added `SQLiteRealUpstreamAppendVfsUpdateDynamicCorpusTest.php` with a dynamic matrix across 16 appendee prefix sizes, 4 page sizes, 7 initial page counts, and 7 appended-row counts.

Focused coverage:
- 3,136 dynamic behavior cases plus 2 guard/provenance cases.
- Focused command: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamAppendVfsUpdateDynamicCorpusTest.php`
- Result: `1 test files, 65864 assertions, 0 failures`
- PASS-line growth: +3,138 focused TestRunner PASS cases.

Non-overlap:
- This extends the existing `avfs.test` appendvfs coverage with the upstream `avfs-4.3` existing-database mutation path.
- It does not repeat accepted `io.test` transaction-sync matrix, IOERR pointer-map, mmap remainder, rollback-journal apply, VFS writer/sync/lock, WAL checkpoint/savepoint, JSON table, B-tree, SELECT, Unicode GLOB, or the existing appendvfs create/grow/shrink/tiny-open coverage.

Dependency closure:
- No new support component is needed. This reuses the lane-local appendvfs offset/trailer model and adds the missing existing appendvfs update transition.
