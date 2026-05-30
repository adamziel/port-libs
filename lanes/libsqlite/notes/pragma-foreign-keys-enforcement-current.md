# PRAGMA foreign_keys enforcement current

This slice adds bounded current-source enforcement for `PRAGMA foreign_keys`
around copied row-array inserts. `SQLitePragmaForeignKeysEnforcement` reuses
the existing `SQLitePragmaForeignKeyCheck` comparison engine so affinity,
collation, composite keys, and NULL child-key short-circuit behavior stay
aligned with the accepted `foreign_key_check` corpus.

Verification:

- Red-first: `php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaForeignKeysEnforcementCurrentTest.php`
  - Before implementation: `1 test files, 4 assertions, 16 failures`
- Focused passing: `php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaForeignKeysEnforcementCurrentTest.php`
  - After implementation: `1 test files, 21 assertions, 0 failures`
- Related FK family: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteForeignKeyDeferredCascadeCorpusTest.php lanes/libsqlite/tests/SQLiteForeignKeyOnUpdateCorpusTest.php lanes/libsqlite/tests/SQLitePragmaForeignKeyCheckCorpusTest.php lanes/libsqlite/tests/SQLitePragmaForeignKeysEnforcementCurrentTest.php`
  - Passed: `4 test files, 186 assertions, 0 failures`
- Example smoke: `php lanes/libsqlite/examples/wordpress-pragma-foreign-keys-enforcement-current.php --self-test`
  - `wordpress-pragma-foreign-keys-enforcement-current self-test passed`
- PHP lint:
  - `php -l lanes/libsqlite/src/SQLitePragmaForeignKeysEnforcement.php`
  - `php -l lanes/libsqlite/tests/SQLitePragmaForeignKeysEnforcementCurrentTest.php`
  - `php -l lanes/libsqlite/examples/wordpress-pragma-foreign-keys-enforcement-current.php`
- Diff check: `git diff --check -- lanes/libsqlite`

Non-overlap: this does not change PRAGMA index_xinfo/foreign-key evidence,
suite-evidence accounting, window frame validation, or dashboard/root
publication files. It only adds insert-time FK enforcement gates over the
existing row-array `foreign_key_check` behavior.

Dependency closure: no new support component is needed; the slice reuses the
accepted native PHP foreign-key check helper.
