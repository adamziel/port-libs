# real-upstream-corpus-trigger-fkey-dynamic-20260601T122004Z-0

Slice: `real-upstream-corpus-trigger-fkey-dynamic-20260601T122004Z-0`

Source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_fkey.test`
- Ported upstream cases: `e_fkey-34.1..34.33` and `e_fkey-35.1..35.3`

Behavior added:

- Added `SQLiteDynamicTriggerForeignKeyPlan::eForeignKeyDeferrableClauseMatrixPlan()` for the `e_fkey-34` seven-table clause matrix.
- The matrix preserves SQLite's rule that only `DEFERRABLE INITIALLY DEFERRED` is deferred; `NOT DEFERRABLE INITIALLY DEFERRED`, `NOT DEFERRABLE INITIALLY IMMEDIATE`, `NOT DEFERRABLE`, `DEFERRABLE INITIALLY IMMEDIATE`, bare `DEFERRABLE`, and an omitted clause all fail at the statement boundary.
- The same plan models the `e_fkey-35` deferred `track` / `artist` repair example: the first `COMMIT` fails, the transaction stays open, inserting the missing parent lets the final `COMMIT` succeed.

Focused evidence:

- `php -l lanes/libsqlite/src/SQLiteDynamicTriggerForeignKeyPlan.php` - no syntax errors.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicDeferrableClauseMatrix20260601Test.php` - no syntax errors.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicDeferrableClauseMatrix20260601Test.php` - `1 test files, 66016 assertions, 0 failures`; `1005` PASS lines.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php` - `1 test files, 6 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicSection6Limits20260531Test.php` - `1 test files, 36617 assertions, 0 failures`.

Status delta:

- `phpPass`: `5887953 -> 5888958` (`+1005` verified focused PASS lines).
- Mapped upstream coverage remains `1589 / 1589`; this is new focused behavior growth over already hydrated upstream source.
- Broad release/all remains at the existing 7 known failures and was not rerun for this isolated micro-slice.

Non-overlap:

- This covers `e_fkey.test` section 4.2 deferrable-clause timing and deferred repair.
- It avoids accepted `e_fkey-31` immediate trigger repair, `e_fkey-62` `SET CONSTRAINTS`/fixed timing coverage, `e_fkey-63` trigger-depth limits, `e_fkey-64` recursive trigger toggles, and the quoted cascade / implicit DROP TABLE trigger-FK slices.

Dependency closure:

- No new support component is needed. The batch extends the existing generic dynamic trigger/FK planner and focused TestRunner coverage only.
