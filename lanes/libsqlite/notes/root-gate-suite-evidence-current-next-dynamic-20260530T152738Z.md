# Root Gate Suite Evidence Current-Next Dynamic

## Delta

- Tightened `SQLiteUpstreamSuiteEvidence::suiteEvidenceSlice()` so one slice can preserve any number of already-counted rows but can advance only one bounded evidence row.
- Added focused dynamic coverage for a two-row advance attempt. The record is now blocked with `suite-evidence-multiple-advanced-rows`, keeps `mapped_delta` and `php_pass_delta` at `0`, and does not set either current-next count key.

## Evidence

- Red before source fix: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteSuiteEvidenceCurrentNextDynamicTest.php` failed `current next dynamic evidence blocks multiple advanced rows in one slice` because the record was admitted as `current-next108-suite-evidence-countable`.
- Passing after source fix:
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteSuiteEvidenceCurrentNextDynamicTest.php lanes/libsqlite/tests/SQLiteSuiteEvidenceCurrentNextDynamicRangeTest.php lanes/libsqlite/tests/SQLiteRootGateSuiteEvidenceWindowCurrentNextRegressionTest.php` -> `3 test files, 320 assertions, 0 failures`.
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteSuiteEvidenceCurrentNext78Test.php lanes/libsqlite/tests/SQLiteSuiteEvidenceCurrentNext79Test.php lanes/libsqlite/tests/SQLiteSuiteEvidenceCurrentNext80Test.php lanes/libsqlite/tests/SQLiteSuiteEvidenceCurrentNext81Test.php lanes/libsqlite/tests/SQLiteSuiteEvidenceCurrentNext82Test.php lanes/libsqlite/tests/SQLiteSuiteEvidenceCurrentNext83Test.php lanes/libsqlite/tests/SQLiteSuiteEvidenceCurrentNext84Test.php lanes/libsqlite/tests/SQLiteSuiteEvidenceCurrentNext85Test.php lanes/libsqlite/tests/SQLiteSuiteEvidenceCurrentNext86Test.php lanes/libsqlite/tests/SQLiteSuiteEvidenceCurrentNext87Test.php lanes/libsqlite/tests/SQLiteSuiteEvidenceCurrentNext88Test.php lanes/libsqlite/tests/SQLiteSuiteEvidenceCurrentNext89Test.php lanes/libsqlite/tests/SQLiteSuiteEvidenceCurrentNext90Test.php lanes/libsqlite/tests/SQLiteSuiteEvidenceCurrentNext91Test.php lanes/libsqlite/tests/SQLiteSuiteEvidenceCurrentNext92Test.php lanes/libsqlite/tests/SQLiteSuiteEvidenceCurrentNext93Test.php lanes/libsqlite/tests/SQLiteSuiteEvidenceCurrentNext94Test.php lanes/libsqlite/tests/SQLiteSuiteEvidenceCurrentNext95Test.php lanes/libsqlite/tests/SQLiteSuiteEvidenceCurrentNext96Test.php lanes/libsqlite/tests/SQLiteSuiteEvidenceCurrentNext97Test.php lanes/libsqlite/tests/SQLiteSuiteEvidenceCurrentNext98Test.php lanes/libsqlite/tests/SQLiteSuiteEvidenceCurrentNext99Test.php lanes/libsqlite/tests/SQLiteSuiteEvidenceCurrentNext100Test.php lanes/libsqlite/tests/SQLiteSuiteEvidenceCurrentNext101Test.php lanes/libsqlite/tests/SQLiteSuiteEvidenceCurrentNext102Test.php lanes/libsqlite/tests/SQLiteSuiteEvidenceCurrentNext103Test.php` -> `26 test files, 1115 assertions, 0 failures`.
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoWordPressSpecificApiTest.php` -> `1 test files, 3 assertions, 0 failures`.

## Dependency Closure

No new support component needed. The change reuses lane-local artifact rows, focused TestRunner PASS-line admission, and the existing active-runner gate.
