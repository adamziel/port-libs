# real-upstream-corpus-upsert-returning-dynamic-20260530T182844Z-0

Upstream source file:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert4.test`
  - Ported conflict target analysis scenarios `upsert4-2.1` through `upsert4-2.9`.
  - Ported expression-index target scenarios `upsert4-3.2`, `upsert4-3.4`, and `upsert4-3.5`.
  - Ported partial unique-index target scenarios `upsert4-4.2` through `upsert4-4.5`.
  - Ported expression collation mismatch rejection scenario `upsert4-5.0`.

Focused assertion count:

- Added `1021` focused PHP TestRunner assertions in `SQLiteRealUpstreamUpsertReturningDynamicTargetAnalysisTest.php`.

Non-overlap:

- This batch does not repeat accepted `upsert5.test` generalized multi-arm ordering, `returning1.test` row-stream recomputation, UPSERT priority, or statement-rowid RETURNING coverage.
- It focuses specifically on `upsert4.test` conflict target matching: collation-sensitive target identity, catch-all fallback, insert-time unique conflicts when a targeted arm does not match, expression index target identity, and partial unique-index predicate identity.

Dependency closure:

- No new support component is needed. The slice reuses the existing native PHP UPSERT conflict-arm executor and RETURNING-compatible result model.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningDynamicTargetAnalysisTest.php` passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningDynamicTargetAnalysisTest.php` passed with `1 test files / 1021 assertions / 0 failures`.
- Root harness not run; isolated micro-slice only.
