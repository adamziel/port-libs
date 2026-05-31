## Application WAL Rollback JSON Dynamic Parity

Base accepted HEAD: `7174979f2808c9ccf08c3331545660695c77e192`.

This slice extends `SQLiteJsonImportRollbackWalPlan` with dynamic
tenant-collision scenarios for application JSON import batches in WAL mode.
The new generator creates same-key rows across two generic tenants, mutates
only the target tenant, then rolls the batch back after a malformed JSON
statement. The focused assertions verify that rollback restores the original
database bytes, truncates the WAL, discards only the target tenant page, keeps
the stable tenant page out of WAL rollback, and preserves JSON text versus
JSONB behavior.

Non-overlap: this does not repeat previous app-WAL dynamic parity cases for
plain rollback, preexisting WAL prefixes, deferred failure, retry, missing WAL
tail, or partial WAL tail. It adds same-key tenant isolation around the
existing source-neutral `tenant_id`/`key_name` surfaces.

Verification:

- `php -d memory_limit=1024M tools/run-tests.php lanes/libsqlite/tests/SQLiteApplicationWalRollbackJsonDynamicParityTest.php`
  - before this slice: `1 test files, 2458 assertions, 0 failures`
  - after this slice: `1 test files, 2825 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-wal-rollback-json-dynamic-parity.php --self-test`
- `php -l lanes/libsqlite/src/SQLiteJsonImportRollbackWalPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteApplicationWalRollbackJsonDynamicParityTest.php`
- `php -l lanes/libsqlite/examples/application-wal-rollback-json-dynamic-parity.php`
- `git diff --check -- lanes/libsqlite`

Dependency closure: no new support component is needed. The slice reuses the
existing native JSON mutation, savepoint, WAL rollback, and source-neutral
tenant key handling.
