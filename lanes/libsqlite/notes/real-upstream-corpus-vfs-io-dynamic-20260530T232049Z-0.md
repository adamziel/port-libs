# real-upstream-corpus-vfs-io-dynamic-20260530T232049Z-0

Base accepted HEAD: `97bde16e3221376c9c3d6c7f9b2330b164322c56`.

This slice ports a non-overlapping VFS I/O cluster from the hydrated upstream
SQLite corpus file `/home/claude/port-libs/.upstream-cache/libsqlite/test/io.test`.
It adds `SQLiteRealUpstreamCorpusVfsIoDynamicSectorSafeAppendTest.php` with
1,001 distinct TestRunner cases and 11,002 focused assertions covering:

- `io.test` `io-2.9.1` through `io-2.9.3`: sector sizes larger than page sizes
  disable atomic-write journal elision.
- `io.test` `io-2.10.1` through `io-2.10.3`: specific atomic device capability
  flags gate whether a single-page write may skip the rollback journal.
- `io.test` `io-3.1` through `io-3.3`: `IOCAP_SEQUENTIAL` cache spill grows the
  file before commit while deferring syncs until the commit database sync.
- `io.test` `io-4.1` through `io-4.3`: `IOCAP_SAFE_APPEND` uses the `0xffffffff`
  journal-header record count, avoids extra spill headers, and keeps the
  expected sync target sequence.

The batch reuses the existing generic `SQLiteVfsIoDynamicPlan` behavior model.
No new support component is needed. No WordPress-specific API or scenario is
added.

Expected dashboard movement: +1,001 focused PHP TestRunner PASS cases if the
integrator admits this file. Mapped coverage remains `1589 / 1589` because the
upstream denominator is already fully mapped.

Focused verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsIoDynamicSectorSafeAppendTest.php`
  passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsIoDynamicSectorSafeAppendTest.php`
  passed: `1 test files, 11002 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  passed: `1 test files, 3 assertions, 0 failures`.
- `git diff --check -- lanes/libsqlite` passed.
