# real-upstream-corpus-date-affinity-dynamic-20260531T000505Z-0

- Base accepted HEAD: `dd1b1090c602dc6e35c0593d57edce4faedf25d2`.
- Upstream source: `/home/claude/port-libs/.upstream-cache/libsqlite/test/date2.test`.
- Ported sections: `date2-300`, `date2-310`, `date2-320`, `date2-330`, and `date2-331`.
- Added focused PHP coverage: `SQLiteRealUpstreamCorpusDateAffinityDynamicDate2IndexRows20260531T000505ZTest.php`.
- New PASS-line movement: `+1002` focused TestRunner PASS cases.
- New behavior assertions: `4006`.
- Non-overlap: this slice covers the upstream `t3` indexed `datetime(b)` row matrix and selected `date2-331` range result set. It does not add more date3 auto/unixepoch loops, date4 strftime matrix rows, or date5 Gregorian-cycle rows already covered by existing accepted files.
- Dependency closure: no new support component is needed. The slice reuses existing `SQLiteCoreScalarFunction` date/time, `typeof`, and deterministic-function checks.
- Verification:
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusDateAffinityDynamicDate2IndexRows20260531T000505ZTest.php` => `1 test files, 4006 assertions, 0 failures`.
  - `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusDateAffinityDynamicDate2IndexRows20260531T000505ZTest.php` => no syntax errors.
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php` => passed.
  - `git diff --check -- lanes/libsqlite` => passed.

