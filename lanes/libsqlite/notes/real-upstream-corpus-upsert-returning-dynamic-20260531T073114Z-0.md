# real-upstream-corpus-upsert-returning-dynamic-20260531T073114Z-0

Base accepted HEAD: `49647c646cee956ed1d4c9609a0c5aac0efc4e84`.

Added a focused real-upstream UPSERT/RETURNING replay corpus:

- Source truth:
  - `/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert5.test`
    scenarios `1.$tn.100` through `1.$tn.505` for conflict-arm priority,
    repeated targets, catchall arms, and `DO NOTHING` suppression.
  - `/home/claude/port-libs/.upstream-cache/libsqlite/test/returning1.test`
    scenario family `17.$tn` for RETURNING projection/yield stream semantics.
- New focused file:
  - `lanes/libsqlite/tests/SQLiteRealUpstreamCorpusUpsertReturningDynamicReplayTest.php`
- Focused movement:
  - `1025` TestRunner PASS cases.
  - `7171` behavior assertions.
  - `phpPass` candidate movement: `2693523 -> 2694548`.
- Non-overlap:
  - This does not repeat the recent trigger-old-image, catchall-only,
    excluded-alias, conflict-target-admission, target-first, or broad upsert5
    static matrix files. It exercises dynamic statement-state replay across
    seeded application rows with RETURNING projections and yield trace
    ordering.
- Dependency closure:
  - No new support component is needed. The slice reuses
    `SQLiteUpsertDoUpdateWherePlan::executeConflictArmsWithYieldTrace()` and
    `SQLiteUpsertDoUpdateWherePlan::returningRows()`.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusUpsertReturningDynamicReplayTest.php`
  - `No syntax errors detected`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusUpsertReturningDynamicReplayTest.php`
  - `1 test files, 7171 assertions, 0 failures`
  - `1025` PASS lines

Root harness: not run - isolated micro-slice.
