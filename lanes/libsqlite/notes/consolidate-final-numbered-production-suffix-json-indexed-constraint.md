# JSON indexed-constraint helper consolidation

Consolidated the private `SQLiteJsonTablePlan` indexed-constraint cost helper
group from numbered `...119` production method names into stable descriptive
helper names. Public entry points, returned metadata keys, action labels, and
test names are preserved.

Verification:

- `php -l lanes/libsqlite/src/SQLiteJsonTablePlan.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableIndexedConstraintCostTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTable*Test.php`
- `git diff --check -- lanes/libsqlite`

Dependency closure: no new support component is needed; this is a production
helper-name consolidation only.
