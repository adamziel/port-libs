# real-upstream-corpus-upsert-returning-dynamic-20260530T221321Z-0

Base accepted HEAD: `661e026d244a8143c42a9b42e699177ff26e29f3`.

Implemented a bounded generic PHP behavior model for upstream `returning1.test`
sections 20.1 through 20.3. The covered behavior is `DELETE ... RETURNING`
with correlated aggregate subqueries against the modified table: each yielded
RETURNING row observes the table image after that row has been deleted, and
correlated expressions may still reference the deleted row.

Non-overlap:
- Avoids existing `SQLiteRealUpstreamUpsertReturningNoTargetDynamicTest.php`,
  which owns `returning1.test` 17 and `upsert2.test` 200 no-target UPSERT
  streams.
- Avoids existing `SQLiteRealUpstreamUpsertReturningDynamicWideMatrixTest.php`,
  which owns `upsert5.test` conflict-arm ordering and returning rowid stream
  matrices.

Focused evidence:
- `php -l lanes/libsqlite/src/SQLiteReturningCorrelatedSubqueryPlan.php`
  passed.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamReturningCorrelatedSubqueryDynamicTest.php`
  passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamReturningCorrelatedSubqueryDynamicTest.php`
  passed with `1 test files, 3007 assertions, 0 failures`.
- The focused test emits 1005 real TestRunner PASS cases.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoWordPressSpecificApiTest.php`
  was attempted but the guard file is absent in this worktree.

Dependency closure:
No new support component is needed. The batch adds a lane-local generic
`SQLiteReturningCorrelatedSubqueryPlan` helper and reuses existing focused
TestRunner infrastructure.
