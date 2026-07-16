# real-upstream-corpus-vfs-io-dynamic-20260530T235125Z-0

Base accepted HEAD: `8c54cf5d7498c37ac92862dd579a0f2d540ceb41`.

Upstream source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/ioerr.test`.

Ported scenarios:

- `ioerr.test` `ioerr-12` incremental vacuum page release.
- `ioerr.test` `ioerr-12` coresident sector journaling.
- `ioerr.test` `ioerr-13` `balance_quick` pointer-map update.
- `ioerr.test` `ioerr-14` `balance_deeper` pointer-map update.
- `ioerr.test` `ioerr-15` index delete plus overflow insert.
- `ioerr.test` `ioerr-16` vacuum incremental cache-spill branch.

Behavior added:

- `SQLiteVfsIoDynamicPlan::autoVacuumIoErrorProfile()` now recognizes those real upstream `ioerr.test` roots.
- Existing focused corpus `SQLiteRealUpstreamCorpusVfsIoerrPointerMapDynamicTest.php` is preserved and extended. The new section sweeps 6 scenarios across 30 fault indices and 7 VFS operations (`read`, `write`, `sync`, `truncate`, `delete`, `open`, `access`) for 1260 dynamic behavior PASS cases plus citation and malformed-input guard cases.
- Expected movement: +1262 focused TestRunner PASS cases and +31530 focused assertions over the accepted file. This is PASS-line growth only; mapped denominator remains `1589 / 1589`.

Non-overlap:

- Does not repeat the existing `autovacuum_ioerr2.test` / `incrvacuum_ioerr.test` dynamic corpus.
- Does not add metadata-only rows or fabricated script ids.
- Does not add WordPress-specific APIs or source names.

Verification:

- `php -l lanes/libsqlite/src/SQLiteVfsIoDynamicPlan.php` passed.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsIoerrPointerMapDynamicTest.php` passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsIoerrPointerMapDynamicTest.php` passed: `1 test files, 63764 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsAutoVacuumIoerrDynamicTest.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsIoerrPointerMapDynamicTest.php` passed: `2 test files, 90212 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php` passed: `1 test files, 3 assertions, 0 failures`.
- `php -r 'json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'` passed.
- `git diff --check -- lanes/libsqlite` passed.

Dependency closure:

- No new support component is needed. The slice reuses the existing bounded `SQLiteVfsIoDynamicPlan` VFS I/O fault model and extends it with real upstream `ioerr.test` roots.
