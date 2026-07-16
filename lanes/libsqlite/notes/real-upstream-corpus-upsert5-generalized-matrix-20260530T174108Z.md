# real-upstream-corpus-upsert-returning-dynamic-20260530T174108Z-0

Base: `e12ceba2fd83282957420709bd781aee710bc7ca`

Added focused real upstream coverage for SQLite `test/upsert5.test`, specifically the generalized UPSERT `1.*` matrix cases `100` through `505` across the six upstream table variants. The PHP coverage checks native conflict-arm behavior for final row images, RETURNING row emission/suppression, matched arm selection, duplicate arm precedence, default `ON CONFLICT` arms, `DO NOTHING`, and change counts.

Focused verification:

```text
php -l lanes/libsqlite/tests/SQLiteRealUpstreamUpsert5GeneralizedMatrixTest.php
No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamUpsert5GeneralizedMatrixTest.php

php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamUpsert5GeneralizedMatrixTest.php
1 test files, 1599 assertions, 0 failures
685 PASS lines
```

Non-overlap: this extends the existing UPSERT/RETURNING dynamic coverage with the broader `upsert5.test` generalized matrix. It does not repeat the existing selected `upsert5-1.1` through `3.1` helper cases as metadata-only rows; each new test executes the native conflict-arm planner with a real upstream case shape.

Dependency closure: no new support component is needed. The slice reuses the native `SQLiteUpsertDoUpdateWherePlan::executeConflictArms()` and `returningRows()` behavior.
