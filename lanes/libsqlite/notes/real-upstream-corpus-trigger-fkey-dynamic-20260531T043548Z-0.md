# real-upstream-corpus-trigger-fkey-dynamic-20260531T043548Z-0

Status: ready for integration from accepted base `7db59d242cf2590641e3217c1b87d71727256c92`.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey2.test`
- `fkey2-3.1.1..3.1.5`: FK `ON UPDATE CASCADE` opens a statement transaction and rolls back the parent, child, and grandchild updates when the downstream `CHECK (e!=5)` constraint fails.
- `fkey2-3.2.1..3.2.2`: FK `ON DELETE CASCADE` opens a statement transaction and rolls back the parent and child deletes when the remaining grandchild row would violate its FK.

Implementation:

- Added `SQLiteDynamicTriggerForeignKeyPlan::fkeyActionStatementTransactionPlan()` for the `fkey2-3.*` action rollback behavior.
- Added `SQLiteRealUpstreamTriggerFkeyDynamicStatementTransactionTest.php` with 4,735 focused assertions over dynamic parent/key seeds.

Verification:

- `php -l lanes/libsqlite/src/SQLiteDynamicTriggerForeignKeyPlan.php` passed.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicStatementTransactionTest.php` passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicStatementTransactionTest.php` passed: `1 test files, 4735 assertions, 0 failures`.
- `git diff --check -- lanes/libsqlite` passed.

Expected dashboard movement:

- `phpPass`: `2098495 -> 2103230` if this focused real-upstream test file is accepted.
- `benchmarkDenominator.mapped`: unchanged at `1589 / 1589`; mapped inventory is already complete.

Non-overlap:

- Does not repeat accepted trigger/FK DDL `fkey2-14.*`, recursive cascade `fkey2-4.*`, deferred graph `fkey2-2.*`, counter/self-reference `fkey2-15.*`/`fkey2-16.*`, conflict policy `fkey2-20.*`, count_changes `fkey2-17.*`, action-journal `fkey8.*`, quoted cascade `fkey1.*`, trigger RAISE, triggerD rowid/schema binding, triggerupfrom, or recursive trigger/view/upsert-returning coverage.
- This slice owns only upstream `fkey2-3.*` FK action statement-transaction rollback behavior.

Dependency closure:

- No new support component is needed. The slice reuses lane-local trigger/FK planning helpers and existing row-array behavior models.
