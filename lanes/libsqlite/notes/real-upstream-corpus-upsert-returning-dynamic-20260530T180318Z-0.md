# real-upstream-corpus-upsert-returning-dynamic-20260530T180318Z-0

Upstream source files:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/returning1.test`
  - Ported `returning1-17.1` and `returning1-17.2`: multi-row `INSERT ... ON CONFLICT DO UPDATE RETURNING fooid`, including in-statement duplicate rows whose `RETURNING` rowid points back to the updated first insert.
  - Ported `returning1-20.1` through `returning1-20.3`: `RETURNING` subquery recomputation while a `DELETE` statement advances, including correlated outer-row terms and empty final aggregate values.

Focused assertion count:

- Added `91` focused PHP TestRunner assertions in `SQLiteRealUpstreamUpsertReturningDynamicStatementTest.php`.

Non-overlap:

- This batch does not repeat the earlier `upsert5.test` multi-arm matrix, `upsert2.test`/`upsert3.test` repeated-conflict follow-up, trigger/view `RETURNING`, row-value `RETURNING`, or recursive UPSERT current-source slices.
- The new assertions target statement-advancing dynamic `RETURNING` streams from real upstream `returning1.test` sections 17 and 20.

Dependency closure:

- No new support component is needed. The slice reuses existing native PHP UPSERT conflict handling and row-array statement-stream modeling in tests.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningDynamicStatementTest.php` passed with `1 test files / 91 assertions / 0 failures`.
- Root harness not run; isolated micro-slice only.
