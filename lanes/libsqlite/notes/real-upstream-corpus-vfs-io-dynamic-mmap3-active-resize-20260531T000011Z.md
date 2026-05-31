# real-upstream-corpus-vfs-io-dynamic mmap3 active resize

Base accepted HEAD: `8c83cd38b21e6ef37afec24c7a1c1aa06c561658`

Slice: `real-upstream-corpus-vfs-io-dynamic-20260531T000011Z-0`

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/mmap3.test`
- Sections: `mmap3-1.0`, `mmap3-1.2`, `mmap3-1.3`, `mmap3-1.4`, `mmap3-1.5`, `mmap3-1.6`, `mmap3-1.7`, `mmap3-1.8`

Behavior added:

- `SQLiteVfsIoDynamicPlan::mmapActiveStatementResizeProfile()` models the mmap3 PRAGMA `mmap_size` boundary where direct changes are applied immediately, but shrink/disable requests are deferred while a statement is scanning pages.
- The dynamic focused test covers direct shrink, direct growth, active-cursor shrink deferral, active-cursor disable deferral, in-scan retained-size reporting, post-cursor disable, and active growth from zero.
- This is non-overlapping with existing `mmap1.test` read-growth/truncation, `mmap2.test` syscall-failure, `mmapfault.test`, `bigmmap.test`, `mmapwarm.test`, and `mmapcorrupt.test` coverage already present in the accepted base.

Focused evidence:

- `php -l lanes/libsqlite/src/SQLiteVfsIoDynamicPlan.php` passed.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsMmap3ActiveResizeDynamicTest.php` passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsMmap3ActiveResizeDynamicTest.php` passed: `1 test files, 20027 assertions, 0 failures`, `1003` PASS lines.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php` passed: `1 test files, 3 assertions, 0 failures`.
- `git diff --check -- lanes/libsqlite` passed.

Expected selected movement:

- `+1003` focused TestRunner PASS lines.
- `lane-status.json` moves `phpPass` from `1262570` to `1263573`.
- Mapped coverage remains `1589 / 1589`; this is PASS-line growth against already mapped upstream VFS/mmap inventory.

Dependency closure:

- No new support component is needed. The slice reuses existing VFS/mmap planning helpers under `SQLiteVfsIoDynamicPlan`.

Follow-up:

- Remaining non-overlapping VFS I/O corpus candidates include `mmap4.test` and deeper file-system fault sections not already covered by the accepted mmap, walvfs, cksumvfs, ioerr, pagerfault, and append-VFS tests.
