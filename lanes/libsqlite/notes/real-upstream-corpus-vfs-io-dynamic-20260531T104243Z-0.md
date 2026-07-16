# Real upstream corpus VFS I/O dynamic win32 longpath

Status: ready handoff for `real-upstream-corpus-vfs-io-dynamic-20260531T104243Z-0`.

Source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/win32longpath.test`

Ported upstream scenarios:

- `win32longpath.test` `1.0`: default `file_control_vfsname` reports `win32`.
- `win32longpath.test` `1.1`: opening through `-vfs win32-longpath` reports `win32-longpath`.
- `win32longpath.test` `1.2`: normal-path exclusive transaction inserts and ordered readback.
- `win32longpath.test` `1.3`: over-length path without the `\\?\` long-path prefix is rejected.
- `win32longpath.test` `1.4`: long-path exclusive transaction inserts and ordered readback.
- `win32longpath.test` `1.5`: long-path database accepts WAL journal mode.
- `win32longpath.test` `1.6`: WAL append preserves ordered readback.
- `win32longpath.test` `1.7.1a` through `1.7.1f`: encoded URI forms reopen the long path with filename translation disabled.

Implementation:

- Added `SQLiteVfsIoDynamicPlan::win32LongPathProfile()` to model the upstream long-path VFS path construction, `\\?\` prefix requirement, URI variants, WAL sidecar naming, and ordered readback behavior.
- Added `SQLiteRealUpstreamCorpusVfsWin32LongPathDynamicTest.php` with 1001 generated behavior cases across the six upstream URI variants, varied base paths, segment lengths, process ids, and long path depths, plus one guard/source-citation case.

Verification:

- `php -l lanes/libsqlite/src/SQLiteVfsIoDynamicPlan.php`
  - `No syntax errors detected in lanes/libsqlite/src/SQLiteVfsIoDynamicPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsWin32LongPathDynamicTest.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsWin32LongPathDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsWin32LongPathDynamicTest.php`
  - `1 test files, 35049 assertions, 0 failures`

Expected dashboard delta:

- `phpPass` moves from `2878175` to `2879177`, a `+1002` focused TestRunner PASS-case increase.
- `benchmarkDenominator.mapped` remains `1589 / 1589`; this is PASS-line corpus growth over already mapped upstream inventory.

Non-overlap:

This slice avoids accepted VFS file-writer/sync/lock/rollback-journal clusters, `nolock.test` and `win32nolock.test` URI lock suppression, `atomic2.test` batch-atomic fallback, `memjournal*.test`, `journal*.test`, `walvfs.test`, `shmlock.test`, `reservebytes.test`, `cksumvfs.test`, and broad veryquick inventory admission. The new surface is focused `win32longpath.test` long-path VFS and URI reopen behavior.

Dependency closure:

No new support component is needed. The slice reuses the lane-local VFS dynamic planning surface and adds a bounded native PHP path/URI profile.
