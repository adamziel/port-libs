## Source-Neutral Trigger/Upsert Defaults

Slice: `source-neutral-src-trigger-upsert-view-defaults-dynamic-20260601T120110Z-0`
Base accepted HEAD: `5b3a92fac14e00372ad9ece599226a1c8024ea79`

### Scope

Neutralized remaining legacy setting-table defaults in six bounded trigger,
UPSERT, recursive RETURNING, savepoint, and foreign-key helper source files:

- `SQLiteDmlTriggerReturningConflictCurrentSourceNextPlan.php`
- `SQLiteRecursiveTriggerReturningSavepointPlan.php`
- `SQLiteTransactionSavepointTriggerRollbackCurrentSourceNextPlan.php`
- `SQLiteTriggerRecursiveReturningSavepointCurrentSourceNextPlan.php`
- `SQLiteTriggerReturningRecursiveUpsertCurrentSourceNextPlan.php`
- `SQLiteUpsertTriggerForeignKeyYieldPlan.php`

The source now derives row keys, row ids, trace names, and foreign-key yield keys
from generic setting columns, caller-provided conflict targets, or generic
`*_id` / `*_name` row metadata rather than baked-in domain names. The existing
source-neutral trigger/upsert/view defaults guard was expanded to cover these
files.

### Verification

- `php -l` passed for all changed PHP source files and the changed source-neutral test.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteSourceNeutralTriggerUpsertViewDefaultsDynamicTest.php lanes/libsqlite/tests/SQLiteDmlTriggerReturningConflictCurrentSourceNext106Test.php lanes/libsqlite/tests/SQLiteTriggerReturningRecursiveSavepointCurrentNext50Test.php lanes/libsqlite/tests/SQLiteTriggerRecursiveReturningSavepointCurrentSourceTest.php` passed: `4 test files, 321 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteTransactionSavepointTriggerRollbackCurrentSourceNext106Test.php lanes/libsqlite/tests/SQLiteUpsertTriggerForeignKeyYieldCurrentNext23Test.php lanes/libsqlite/tests/SQLiteTriggerReturningRecursiveUpsertCurrentSourceNext118Test.php lanes/libsqlite/tests/SQLiteTriggerRecursiveReturningSavepointSourceComparisonTest.php` passed: `4 test files, 620 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteSourceNeutralDomainSpecificOptionClassesDynamicTest.php lanes/libsqlite/tests/SQLiteSourceNeutralTriggerUpsertViewDefaultsDynamicTest.php` passed: `2 test files, 81 assertions, 0 failures`.
- `git diff --check -- lanes/libsqlite` passed.
- `SQLiteNoWordPressSpecificApiTest.php` is not present in this worktree; the available source-neutral trigger/upsert/view defaults guard was run instead.

### Dependency Closure

No new support component is needed. This cleanup reuses existing native trigger,
UPSERT, recursive RETURNING, savepoint, and foreign-key helpers with generic
row-key and row-id discovery.
