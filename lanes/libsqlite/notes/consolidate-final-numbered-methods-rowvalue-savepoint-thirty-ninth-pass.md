# consolidate-final-numbered-methods-rowvalue-savepoint-thirty-ninth-pass

Consolidated the row-value savepoint release follow-up reader entry point and
its private helper suffixes to stable `ReleaseFollowupReadSavepoint` names.
Direct focused tests and the WordPress smoke now call the stable method name.

This is consolidation-only cleanup. It does not add new libsqlite behavior,
does not change `phpPass` or mapped upstream coverage, and keeps the banned
user-named 150 production suffix absent.

Verification:

- `php -l lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRowValueReleaseFollowupReadSavepointTest.php`
- `php -l lanes/libsqlite/examples/wordpress-rowvalue-release-followup-read-savepoint.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueReleaseFollowupReadSavepointTest.php`
- `php lanes/libsqlite/examples/wordpress-rowvalue-release-followup-read-savepoint.php --self-test`
- `git diff --check -- lanes/libsqlite`

Dependency closure: no new support component is needed; the cleanup reuses the
existing row-value UPDATE/DELETE RETURNING executor, savepoint image handling,
and WordPress smoke path.

Non-overlap: avoids the known rowvalue-window family gate and only touches the
row-value savepoint release follow-up reader wrapper/callers.
