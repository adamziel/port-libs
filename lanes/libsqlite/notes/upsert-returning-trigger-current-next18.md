# UPSERT RETURNING Trigger Current Next18

Slice: `yield-sqlite-upsert-returning-trigger-current-next18`

This current-source slice adds bounded native PHP coverage for SQLite UPSERT
RETURNING row images with INSERT/UPDATE triggers. The covered behavior is
disjoint from accepted UPSERT RETURNING secondary-UNIQUE conflict checks and
WHERE-current row coverage: this helper models trigger firing order, skipped
`DO UPDATE WHERE` conflicts, repeated same-statement rows, `UPDATE OF`
filtering, trigger `WHEN` checks, and the SQLite rule that RETURNING rows are
captured from the top-level change before later AFTER-trigger target
mutations.

Verification:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteUpsertReturningTriggerCurrentNext18Test.php
# Focused test run: 1 selected test files (root lock skipped)
# 1 test files, 42 assertions, 0 failures

php tools/run-tests.php lanes/libsqlite/tests/SQLiteUpsertReturningTriggerCurrentNext18Test.php lanes/libsqlite/tests/SQLiteUpsertReturningSqlTest.php lanes/libsqlite/tests/SQLiteUpsertReturningConflictCurrentTest.php
# Focused test run: 3 selected test files (root lock skipped)
# 3 test files, 209 assertions, 0 failures

php lanes/libsqlite/examples/application-upsert-returning-trigger-current.php --self-test
# returningNames: ["siteurl","new_plugin"], returningTouched: ["statement","statement"], changes: 2

php -l lanes/libsqlite/src/SQLiteUpsertReturningTriggerPlan.php
php -l lanes/libsqlite/tests/SQLiteUpsertReturningTriggerCurrentNext18Test.php
php -l lanes/libsqlite/examples/application-upsert-returning-trigger-current.php
# No syntax errors detected in all changed PHP files

git diff --check -- lanes/libsqlite
# clean
```

Expected dashboard movement: `phpPass +42` from the new focused
`SQLiteUpsertReturningTriggerCurrentNext18Test.php` PASS lines once accepted.
No upstream denominator movement is claimed.

Dependency closure: no new support dependency is needed; this reuses the
existing bounded UPSERT DO UPDATE/RETURNING row-array executor.
