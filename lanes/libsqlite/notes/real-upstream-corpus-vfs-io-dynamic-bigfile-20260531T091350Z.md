# real-upstream-corpus-vfs-io-dynamic-bigfile-20260531T091350Z

Micro-slice: `real-upstream-corpus-vfs-io-dynamic-20260531T091350Z-0`

Base accepted HEAD: `0843201ab605ca08cd36b696d17e3fcdd999de22`

## Upstream source truth

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/bigfile.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/bigfile2.test`

Ported scenarios:

- `bigfile.test` `bigfile-1.1` through `bigfile-1.16`: sparse 4096/8192/16384 MiB database images with a cleared header page-count, reopening from actual file size, copy-table writes beyond 4 GiB, and checksum preservation after reopen.
- `bigfile2.test` `1.1` through `1.3`: fake 4096 MiB plus 14 byte database image with cleared header page-count, append/read of a large overflow payload whose pages start beyond the 4 GiB boundary.

## Patch shape

- Added `SQLiteVfsIoDynamicPlan::largeFileBoundaryProfile()` to model the upstream large-file VFS boundary behavior without allocating sparse multi-GiB fixtures.
- Added `SQLiteRealUpstreamCorpusVfsBigfileDynamicTest.php` with 600 dynamic `bigfile.test` cases, 600 dynamic `bigfile2.test` cases, one upstream source coverage/count guard, and one malformed-input guard.

Focused PASS growth: `+1202` selected TestRunner PASS cases.

Focused behavior assertions: `41413`.

Mapped coverage movement: none. The upstream scripts are already in the mapped denominator; this handoff adds real PASS-line behavior growth.

## Verification

- `php -l lanes/libsqlite/src/SQLiteVfsIoDynamicPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsBigfileDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsBigfileDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
- `git diff --check -- lanes/libsqlite`
- `php -r 'json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'`

Results:

- Bigfile focused corpus: `1 test files, 41413 assertions, 0 failures`
- No-domain guard: `1 test files, 3 assertions, 0 failures`
- Lane status JSON: `lane-status json ok`
- Diff hygiene: no output from `git diff --check -- lanes/libsqlite`

## Non-overlap

This does not duplicate accepted VFS file writer, locked writer, sync plan/apply, lock state, process lock, rollback-journal apply/commit, WAL byte truncation/checkpoint transaction, `io.test`, `ioerr*`, `sysfault`, `mmap`, `appendvfs`, reservebytes, filectrl, or walvfs corpus batches. It also avoids SQL, JSON, PRAGMA, B-tree, planner, metadata-only admission rows, and fabricated upstream script ids.

## Dependency closure

No new support component is needed. The patch reuses the existing bounded `SQLiteVfsIoDynamicPlan` corpus model and adds a native large-file VFS boundary profile with deterministic arithmetic over real upstream `bigfile*.test` scenarios.
