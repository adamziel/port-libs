# real-upstream-corpus-upsert-returning-yield-dynamic-20260530

Added `SQLiteRealUpstreamUpsertReturningYieldDynamicTest.php` with 1000
distinct dynamic UPSERT/RETURNING yield-trace cases plus one source citation
case.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert5.test`
  sections `1.420` through `1.423`, `1.503` through `1.505`, and `3.0`
  through `3.6`.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/returning1.test`
  section `17.*`.

Behavior covered:

- First matching `ON CONFLICT` arm selection across explicit targets and
  trailing catch-all arms.
- `DO NOTHING` versus `DO UPDATE` RETURNING yield visibility.
- Statement-current RETURNING stream order for insert/update steps.
- Redundant conflict-arm priority without changing the indexed row view.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningYieldDynamicTest.php`
  passed: `1 test files, 4810 assertions, 0 failures`, with `1001` PASS
  lines.

Dependency closure:

- No new support component needed. This reuses the existing native
  `SQLiteUpsertDoUpdateWherePlan::executeConflictArmsWithYieldTrace()`
  behavior and adds real upstream corpus coverage only.

Non-overlap:

- Does not repeat the accepted no-target dynamic row-stream file or the broad
  upsert5/returning1 wide matrix file. This slice owns the yield-trace
  boundary for catch-all, selected-arm, skipped, and RETURNING-visible dynamic
  cases.
