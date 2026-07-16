# Real upstream corpus VFS walvfs dynamic

Slice: `real-upstream-corpus-vfs-io-dynamic-20260530T211645Z-0`

Status: ready as focused PHP TestRunner PASS-line growth.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/walvfs.test`
- Ported scenarios:
  - `walvfs-4.1` and `walvfs-4.2`: readonly SHM map boundaries, including busy shared-lock retry before readonly failure.
  - `walvfs-5.3` through `walvfs-5.6`: read-mark recovery, busy SHM lock retry, readonly+busy failure, and later-reader success after readmark release.
  - `walvfs-6.2`: restart protocol failure when shared locks remain busy.
  - `walvfs-7.1`: checkpoint lock busy result `{1 -1 -1}`.
  - `walvfs-8.3`: version-2 VFS checkpoint refresh of an out-of-date cache.
  - `walvfs-9.1`: readonly-CANTINIT plus shared-lock I/O error boundary.

Implementation:

- Added `SQLiteWalVfsDynamicPlan` as a bounded native PHP model for real upstream WAL VFS SHM map/lock/readmark/checkpoint boundaries.
- Added `SQLiteRealUpstreamCorpusVfsWalvfsDynamicTest.php` with 1,005 distinct focused PASS cases and 14,029 behavior assertions over dynamic busy-attempt and WAL-frame variants.

Non-overlap:

- This does not repeat accepted `walvfs.test` journal-size-limit/checkpoint interrupt coverage, VFS file writer, locked writer, sync plan/apply, rollback-journal apply/commit, WAL byte truncation, WAL checkpoint transaction, appendvfs, `io.test`, `ioerr*.test`, pagerfault, mmapfault, JSON, B-tree, SQL executor, or source-neutral cleanup surfaces.
- The owned upstream section is specifically `walvfs.test` SHM map/lock/readmark/checkpoint boundary behavior from sections 4 through 9.
- Mapped denominator coverage remains unchanged because mapped inventory is already complete; this is PASS-line growth over hydrated upstream behavior.

Verification:

- `php -l lanes/libsqlite/src/SQLiteWalVfsDynamicPlan.php` -> no syntax errors.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsWalvfsDynamicTest.php` -> no syntax errors.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsWalvfsDynamicTest.php` -> `1 test files, 14029 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php` -> passed.
- `git diff --check -- lanes/libsqlite` -> passed.

Dependency closure:

- No new support component is required. The slice adds lane-local native PHP WAL VFS dynamic planning and uses the existing focused PHP test runner.

Next task:

- Continue VFS I/O work only on a different real upstream section or a full release/all runner blocker. Avoid another `walvfs.test` sections 4-9 matrix unless it wires this behavior into a broader real file-handle transaction application path.
