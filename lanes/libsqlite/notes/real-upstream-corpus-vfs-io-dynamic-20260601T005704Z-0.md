# real-upstream-corpus-vfs-io-dynamic-20260601T005704Z-0

## Source truth

- Upstream file: `/home/claude/port-libs/.upstream-cache/libsqlite/test/crashM.test`.
- Ported behavior: `crashM.test` multiplex VFS crash loop with 8.3 sidecar names, attached `aux` database, `crashsql` abnormal child exit during an attached transaction, and post-crash `PRAGMA main.integrity_check` / `PRAGMA aux.integrity_check` returning `ok`.
- Covered upstream sections: `crashM-1.0` setup plus `crashM-2.0` through `crashM-2.19`.

## Patch delta

- Added `SQLiteVfsIoDynamicPlan::multiplexCrashRecoveryProfile()` for the real `crashM.test` multiplex attached-database recovery profile.
- Added a focused TestRunner corpus with 1000 dynamic `crashM` cases covering all 20 crash-loop iterations across varied row counts, payload sizes, page sizes, and requested chunk sizes.
- Added a generic application example smoke for the multiplex crash-recovery profile.

## Verification

- `php -l lanes/libsqlite/src/SQLiteVfsIoDynamicPlan.php` passed.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsIoCrashMultiplexDynamicTest.php` passed.
- `php -l lanes/libsqlite/examples/application-vfs-multiplex-crash-recovery.php` passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsIoCrashMultiplexDynamicTest.php` passed: `1 test files, 48970 assertions, 0 failures`.

## Non-overlap

This slice ports previously unreferenced `crashM.test` behavior and does not repeat existing `io.test`, `walvfs*.test`, mmap, delete-db, short-name, lock-byte, rollback-journal apply, WAL byte truncation, VFS file-writer, or VFS lock-state focused coverage.

## Dependency closure

No new support component is needed. The implementation reuses the existing VFS dynamic plan surface, multiplex chunk naming, short sidecar naming, rollback-journal, and master-journal modeling.
