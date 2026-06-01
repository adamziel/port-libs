Source-neutral row-value savepoint defaults dynamic cleanup

- Micro-slice: source-neutral-src-rowvalue-savepoint-defaults-dynamic-20260601T070521Z-0
- Base accepted HEAD: cc9294ac19877407e3f202dbdfd54b6a9a8fb67d
- Scope: row-value savepoint wrapper defaults and shared UPDATE/DELETE RETURNING row-id default under lanes/libsqlite/src.

Changes:

- Added SQLiteRowIdColumn as a generic row-id resolver for neutral default `setting_id`, canonical `id`/`rowid`, single-column unique `_id` keys, and stable unique `_id` table shapes.
- Replaced hardcoded/default `option_id` row-id parameters in the bounded row-value savepoint wrappers and SQLiteUpdateDeleteReturningSql with `setting_id`.
- Kept old fixture behavior working without a compatibility alias by resolving legacy-shaped table rows through generic unique row-key evidence.
- Expanded the source-neutral guard to cover the newly owned source files and the shared executor default.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteSourceNeutralRowValueSavepointDefaultsDynamicTest.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php` passed: 2 test files, 36 assertions, 0 failures.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueAbortReturningSavepointCurrentSourceNext140Test.php lanes/libsqlite/tests/SQLiteRowValueConflictReturningDistinctCurrentSourceNextTest.php lanes/libsqlite/tests/SQLiteRowValueConflictReturningSavepointCurrentSourceNext138Test.php lanes/libsqlite/tests/SQLiteRowValueConflictSavepointReturningCurrentSourceNextTest.php lanes/libsqlite/tests/SQLiteRowValueConflictUpsertSavepointCurrentSourceNext136Test.php` passed: 5 test files, 321 assertions, 0 failures.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueDeleteUpdateSavepointCurrentSourceNextTest.php lanes/libsqlite/tests/SQLiteRowValueDeleteUpdateSavepointCurrentSourcePlanTest.php lanes/libsqlite/tests/SQLiteRowValueSavepointReturningDistinctCurrentSourceNextTest.php lanes/libsqlite/tests/SQLiteRowValueUpdateReturningConflictCurrentSourceNext137Test.php lanes/libsqlite/tests/SQLiteRowValueYieldReturningSavepointCurrentSourceNextTest.php lanes/libsqlite/tests/SQLiteRowValueDeleteReturningSavepointCurrentSourceTest.php lanes/libsqlite/tests/SQLiteRowValueSavepointUpsertCurrentSourceNext131Test.php lanes/libsqlite/tests/SQLiteRowValueReturningFailSavepointCurrentSourceNext132Test.php lanes/libsqlite/tests/SQLiteRowValueReturningSavepointConflictCurrentSourceNext128Test.php lanes/libsqlite/tests/SQLiteUpdateDeleteReturningSqlTest.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceTest.php` passed: 11 test files, 655 assertions, 0 failures.

Dependency closure:

- No new support component is needed. This reuses native row-value UPDATE/DELETE RETURNING, UPSERT, conflict handling, and savepoint helpers with a generic row-id resolver.

Follow-up:

- Remaining broad row-value/window/savepoint source files outside this bounded wrapper set still contain legacy `option_id` defaults and should be neutralized in a later source-neutral batch with their own focused family tests.
