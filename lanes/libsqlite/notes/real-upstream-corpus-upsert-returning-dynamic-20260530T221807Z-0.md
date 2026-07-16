# real-upstream-corpus-upsert-returning-dynamic-20260530T221807Z-0

Upstream source file:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert5.test`
  - Ported redundant `ON CONFLICT` index-integrity scenarios `upsert5-3.0` and `upsert5-3.3` through `upsert5-3.6`.
  - This batch extends the accepted modeled seed range with disjoint dynamic seeds `25` through `524`.
  - Focus: `REPLACE` with redundant conflict targets must preserve table scan/index scan agreement and must not apply the redundant update arms.

Focused assertion count:

- Added `3001` focused PHP TestRunner PASS cases and `8501` assertions in `SQLiteRealUpstreamUpsertReturningRedundantConflictExtendedTest.php`.

Non-overlap:

- This does not repeat the accepted default redundant-conflict seed range `1` through `24`, `upsert5` catch-all priority tests, `returning1` scope/name-resolution tests, or multi-row `RETURNING` rowid-stream coverage.
- The new cases use later deterministic seed rows against real upstream `upsert5.test` redundant conflict integrity behavior.

Dependency closure:

- No new support component is needed. The slice reuses the existing native PHP UPSERT redundant-conflict integrity model in `SQLiteUpsertReturningDynamicCorpusPlan`.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningRedundantConflictExtendedTest.php` passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningRedundantConflictExtendedTest.php` passed with `1 test files / 8501 assertions / 0 failures` and `3001` PASS lines.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php` passed with `1 test files / 3 assertions / 0 failures`.
- `git diff --check -- lanes/libsqlite` passed.
- Root harness not run; isolated micro-slice only.
