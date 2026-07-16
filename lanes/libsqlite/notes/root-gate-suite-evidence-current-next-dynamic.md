# root-gate suite evidence current-next dynamic

This slice adds a focused root-gate regression for the current-next78 through
current-next103 suite-evidence family. The regression records one bounded
current-next103 advanced row while preserving current-next78 through
current-next102 rows as already-counted dependency-closure evidence.

The record must expose stable `counts_suite_evidence_current_nextNN` keys for
all prior current-next rows, keep those prior keys false, count only
current-next103, and keep release/all parity explicitly unclaimed.

Focused validation:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteSuiteEvidenceCurrentNextDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteSuiteEvidenceCurrentNext78Test.php lanes/libsqlite/tests/SQLiteSuiteEvidenceCurrentNext79Test.php lanes/libsqlite/tests/SQLiteSuiteEvidenceCurrentNext80Test.php lanes/libsqlite/tests/SQLiteSuiteEvidenceCurrentNext81Test.php lanes/libsqlite/tests/SQLiteSuiteEvidenceCurrentNext82Test.php lanes/libsqlite/tests/SQLiteSuiteEvidenceCurrentNext83Test.php lanes/libsqlite/tests/SQLiteSuiteEvidenceCurrentNext84Test.php lanes/libsqlite/tests/SQLiteSuiteEvidenceCurrentNext85Test.php lanes/libsqlite/tests/SQLiteSuiteEvidenceCurrentNext86Test.php lanes/libsqlite/tests/SQLiteSuiteEvidenceCurrentNext87Test.php lanes/libsqlite/tests/SQLiteSuiteEvidenceCurrentNext88Test.php lanes/libsqlite/tests/SQLiteSuiteEvidenceCurrentNext89Test.php lanes/libsqlite/tests/SQLiteSuiteEvidenceCurrentNext90Test.php lanes/libsqlite/tests/SQLiteSuiteEvidenceCurrentNext91Test.php lanes/libsqlite/tests/SQLiteSuiteEvidenceCurrentNext92Test.php lanes/libsqlite/tests/SQLiteSuiteEvidenceCurrentNext93Test.php lanes/libsqlite/tests/SQLiteSuiteEvidenceCurrentNext94Test.php lanes/libsqlite/tests/SQLiteSuiteEvidenceCurrentNext95Test.php lanes/libsqlite/tests/SQLiteSuiteEvidenceCurrentNext96Test.php lanes/libsqlite/tests/SQLiteSuiteEvidenceCurrentNext97Test.php lanes/libsqlite/tests/SQLiteSuiteEvidenceCurrentNext98Test.php lanes/libsqlite/tests/SQLiteSuiteEvidenceCurrentNext99Test.php lanes/libsqlite/tests/SQLiteSuiteEvidenceCurrentNext100Test.php lanes/libsqlite/tests/SQLiteSuiteEvidenceCurrentNext101Test.php lanes/libsqlite/tests/SQLiteSuiteEvidenceCurrentNext102Test.php lanes/libsqlite/tests/SQLiteSuiteEvidenceCurrentNext103Test.php lanes/libsqlite/tests/SQLiteSuiteEvidenceCurrentNextDynamicTest.php lanes/libsqlite/tests/SQLiteRootGateSuiteEvidenceWindowCurrentNextRegressionTest.php`

Non-overlap: this does not add release/all parity, broad runner admission, or a
new upstream mapped denominator row. It only guards the existing bounded
suite-evidence current-next78 through current-next103 dynamic key and
dependency-closure behavior.

Dependency closure: no new support component needed; the regression reuses the
lane-local `SQLiteUpstreamSuiteEvidence::suiteEvidenceSlice()` record builder
and focused TestRunner PASS-line admission.
