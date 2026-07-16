# real-upstream-corpus-window-functions-dynamic-20260530T203329Z-0

Base accepted HEAD: `d5feb4b8c9f51e52c1a4ee4e369261ca23aa819e`.

Added `SQLiteRealUpstreamWindow8DynamicGroupsCorpusTest.php`, a focused real
upstream corpus port for `/home/claude/port-libs/.upstream-cache/libsqlite/test/window8.test`.
The owned upstream range is `window8.test` sections `1.5.1` through `1.19.8`,
covering the generated `t3` GROUPS frame boundary matrix after the already
represented early `1.1` through `1.4` style sections. The PHP test uses the
real upstream `t3` rows and checks `sum`, `max`, and `min` over `ORDER BY a`
and `ORDER BY a,b` with `NO OTHERS` and `EXCLUDE CURRENT ROW`, comparing
`SQLiteWindowFunction::aggregateFrameBetweenValues()` against an independent
GROUPS-frame oracle.

Focused growth: `14401` assertions in one selected TestRunner file. This is
behavior assertion growth only; no mapped denominator or lane-status counter
was changed in this isolated patch.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamWindow8DynamicGroupsCorpusTest.php`
  passed: `1 test files, 14401 assertions, 0 failures`.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamWindow8DynamicGroupsCorpusTest.php`
  passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  passed: `1 test files, 3 assertions, 0 failures`.
- `git diff --check -- lanes/libsqlite` passed.

The legacy `SQLiteNoWordPressSpecificApiTest.php` guard requested by older
supervisor text is not present on this accepted head; the current
`SQLiteNoDomainSpecificApiTest.php` guard was run instead.

Dependency closure: no new support component is needed. The slice reuses the
existing native PHP `SQLiteWindowFunction` frame implementation and the local
TestRunner.

Root harness: not run - isolated micro-slice.
