# Real Upstream UPSERT Fault Dynamic Corpus

Slice: `real-upstream-corpus-upsert-returning-dynamic-20260531T021958Z-0`

Base accepted HEAD: `a17218f2cb8d9470c5635d8abf1711981a8d7bfc`

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/upsertfault.test`
- Scenario: `upsertfault-1`, after `upsertfault-1.0` creates `t1(a PRIMARY KEY, b, c, d, UNIQUE(b, c))` and seeds two rows, faultsim restores the database and runs `INSERT INTO t1 VALUES(3, 2, 2, NULL) ON CONFLICT(b, c) DO UPDATE SET d=d+1` under OOM faults expecting a successful result.

Patch summary:

- Adds `SQLiteUpsertReturningFaultPlan::recoverableUpsertUpdateFaultCorpus()` for 1000 deterministic real-upstream UPSERT fault variants.
- Adds `SQLiteRealUpstreamUpsertFaultReturningDynamicTest.php` with 5002 focused TestRunner PASS cases and 17003 assertions.
- Non-overlap: existing UPSERT RETURNING dynamic files cover `upsert1.test` through `upsert5.test` priority/conflict matrices and `returning1.test` yield streams. This handoff owns `upsertfault.test` recoverable faultsim behavior.

Focused evidence:

- `php -l lanes/libsqlite/src/SQLiteUpsertReturningFaultPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamUpsertFaultReturningDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamUpsertFaultReturningDynamicTest.php`
  - Result: `1 test files, 17003 assertions, 0 failures`
  - PASS lines: 5002

Expected dashboard movement:

- `phpPass`: `1662211 -> 1667213`
- Mapped coverage remains `1589 / 1589`.

Dependency closure:

- No new support component needed. The slice reuses the existing UPSERT fault planning model and adds a bounded dynamic corpus generator for real upstream `upsertfault.test` behavior.
