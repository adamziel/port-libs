# real-upstream-corpus-btree-index-dynamic-20260601T155158Z-0

Status: ready for focused integration.

Source truth:
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/wherelimit.test`
- Upstream scenarios covered: `wherelimit-0.1` through `wherelimit-0.7`, `wherelimit-1.1` through `wherelimit-1.13`, `wherelimit-2.1` through `wherelimit-2.13`, `wherelimit-3.1` through `wherelimit-3.13`, and `wherelimit-4.2`, `wherelimit-4.3`, `wherelimit-4.11`, `wherelimit-4.12`.

Patch:
- Added `SQLiteWhereLimitMutationPlan` as a generic upstream corpus adapter around the existing lane-local `SQLiteUpdateDeleteLimitPlan`.
- Added `SQLiteRealUpstreamBtreeWhereLimitMutationDynamicTest.php` with 1,200 focused dynamic TestRunner cases plus source-truth/range/invalid-size/dependency checks.

Focused behavior:
- UPDATE/DELETE `ORDER BY` without `LIMIT` diagnostics.
- UPDATE/DELETE target alias behavior.
- `OFFSET` without `LIMIT` diagnostics.
- DELETE `LIMIT`, `ORDER BY`, `OFFSET`, comma `LIMIT offset,count`, and negative limit/offset normalization.
- UPDATE `LIMIT`, `ORDER BY`, `OFFSET`, comma `LIMIT offset,count`, and negative limit/offset normalization.
- DELETE/UPDATE `RETURNING` selected-row projection.
- DELETE `LIMIT` against an INSTEAD OF trigger view.
- DELETE `LIMIT` against a WITHOUT ROWID primary-key B-tree with preserved final ordering.

Non-overlap:
- This does not repeat accepted `wherelimit2.test` or `wherelimit3.test` dynamic coverage. It covers the base upstream `wherelimit.test` file that was not present in the existing B-tree/index dynamic corpus tests.

Dependency closure:
- No new support component is needed; this reuses the existing lane-local UPDATE/DELETE LIMIT selector and generic rowid / WITHOUT ROWID B-tree planning.

Verification:
- `php -l lanes/libsqlite/src/SQLiteWhereLimitMutationPlan.php` - no syntax errors.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamBtreeWhereLimitMutationDynamicTest.php` - no syntax errors.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamBtreeWhereLimitMutationDynamicTest.php` - 1 test files, 60596 assertions, 0 failures.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php` - 1 test files, 7 assertions, 0 failures.
- `git diff --check -- lanes/libsqlite` - passed.
