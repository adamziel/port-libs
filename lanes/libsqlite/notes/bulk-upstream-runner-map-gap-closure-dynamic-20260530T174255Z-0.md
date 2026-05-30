# Bulk upstream runner map gap closure dynamic

Scope: tooling-only upstream runner map gap closure for real hydrated SQLite
`.test` files.

This slice adds `SQLiteUpstreamSuiteEvidence::upstreamRunnerMapGapClosurePlan()`.
The planner reads the hydrated upstream test directory, compares real concrete
`.test` scripts against the current manifest runner selections, and emits the
first bounded unmapped script list plus a guarded `testrunner.tcl --stop-on-error
veryquick` command. It does not add denominator rows, does not claim release/all
parity, and does not fabricate script ids.

Focused evidence:

- Real upstream source: `/home/claude/port-libs/.upstream-cache/libsqlite/test`
- Candidate command size: `1000` real `.test` scripts
- Focused PHP verification: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteUpstreamRunnerMapGapClosurePlanTest.php`
- Result: `1 test files, 3018 assertions, 0 failures`

Dashboard impact:

- PASS-line growth: `+2` focused TestRunner PASS lines from the new focused
  test file.
- Assertion growth: `+3018` focused assertions.
- Mapped denominator growth: `0`; the integrator should count mapped growth
  only after running the generated guarded upstream command and admitting
  zero-error rows.

Dependency closure: no new support component is needed. The planner composes
existing manifest runner selections and real hydrated upstream `.test` files.
