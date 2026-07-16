# real-upstream-corpus-upsert-returning-dynamic-20260531T004029Z-0

Base accepted HEAD: `ad16a572f80ccf85246d93f3ad58ce0402786c09`

Added a generic real-upstream UPSERT/RETURNING dynamic corpus for SQLite
`test/upsert2.test` sections `320/321`, cross-checked against
`test/returning1.test` section `4`: a conflict that reaches `DO UPDATE` but
whose `WHERE` predicate is false must skip the update, emit no `RETURNING` row,
and leave the row image available for a later statement-current conflict.

New focused coverage:

- `SQLiteUpsertReturningDynamicCorpusPlan::upsert2WhereFalseReturningDynamicCases(1000)`
- `SQLiteRealUpstreamUpsertReturningWhereFalseDynamicTest.php`
- 1000 deterministic statement-current streams
- 5002 focused TestRunner PASS lines
- 12003 focused assertions

Verification:

- `php -l lanes/libsqlite/src/SQLiteUpsertReturningDynamicCorpusPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningWhereFalseDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningWhereFalseDynamicTest.php`
  - `1 test files, 12003 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningDynamicArmsCorpusTest.php lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningDynamicCatchAllMatrixTest.php lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningDynamicYieldTraceTest.php lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningCatchAllPriorityDynamicTest.php lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningRedundantConflictIntegrityTest.php lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningRedundantConflictExtendedTest.php lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningWhereFalseDynamicTest.php`
  - `7 test files, 36525 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - `1 test files, 3 assertions, 0 failures`
- `git diff --check -- lanes/libsqlite`
  - passed

Dependency closure: no new support component needed; this reuses
`SQLiteUpsertDoUpdateWherePlan::executeConflictArmsWithYieldTrace()` and the
existing generic UPSERT/RETURNING corpus planner.

Non-overlap: this does not repeat accepted no-target duplicate row streams,
omitted-target `DO NOTHING`, upsert5 catch-all priority, redundant conflict
integrity, trigger old-value, or large duplicate RETURNING yield batches.
