# real-upstream-corpus-vfs-io-dynamic-20260530T233406Z-0

Base accepted HEAD: `d7c5d7f50d0d0c3f24c91125036d23912559b628`.

Added a real upstream checksum-VFS dynamic batch from
`/home/claude/port-libs/.upstream-cache/libsqlite/test/cksumvfs.test`.
The ported sections are `cksumvfs-1.0` through `cksumvfs-1.9`: reserve-byte
page setup, initial row readback, large insert commit, WAL delete/checkpoint,
recursive insert count, restore/reopen count, and close/reopen count.

Focused coverage:

- New source: `SQLiteChecksumVfsPlan::checksumVfsProfile()`.
- New test: `SQLiteRealUpstreamCorpusVfsChecksumDynamicTest.php`.
- Focused PASS-line movement: `+1002` PASS cases.
- Focused behavior assertions: `28008`.
- Upstream scripts owned: `cksumvfs.test` only.
- Non-overlap: does not repeat accepted VFS lock state, file writer, sync
  plan/apply, rollback-journal apply/commit, sysfault, appendvfs, WAL SHM, or
  ioerr batches; this batch is checksum-VFS reserve-byte and WAL checkpoint
  reopen behavior.

Verification:

- `php -l lanes/libsqlite/src/SQLiteChecksumVfsPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsChecksumDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsChecksumDynamicTest.php`
  - Result: `1 test files, 28008 assertions, 0 failures`

Dependency closure: no new support component is needed. The batch reuses the
existing PHP VFS/pager/WAL planning surface and adds a bounded native checksum
VFS profile for upstream reserve-byte checkpoint/reopen behavior.
