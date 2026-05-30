# Row-value UPDATE/DELETE RETURNING savepoint current-source next164

Behavior slice: adds `SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext164Plan` for `UPDATE OR ROLLBACK` row-value assignment conflicts inside a savepoint. The failed conflict cancels the whole savepoint transaction, suppresses speculative RETURNING rows, restores the transaction image, and retries UPDATE/DELETE RETURNING from that restored current source.

Application smoke: `application-rowvalue-rollback-retry-current-source-next164.php --self-test` models copied `wp_options` staging rows where a duplicate `(blog_id, option_name)` conflict rolls back speculative option-name rewrites before retry cleanup.

Verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext164Test.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 61 assertions, 0 failures

php lanes/libsqlite/examples/application-rowvalue-rollback-retry-current-source-next164.php --self-test
application-rowvalue-rollback-retry-current-source-next164 self-test passed
```

Expected dashboard movement: `phpPass` +61 for the new focused PASS lines. Mapped upstream coverage is unchanged because this is a current-source PHP behavior slice over already mapped row-value UPDATE/DELETE RETURNING/savepoint behavior, not a newly hydrated upstream manifest row.

Dependency closure: no new support component needed; this composes existing native PHP row-value UPDATE/DELETE RETURNING parsing, unique-conflict detection, and current-source savepoint modeling.

Non-overlap: avoids accepted next132 `OR FAIL` preservation, next140 `OR ABORT` statement rollback, next156-next158 rollback-to retry, next161 `OR FAIL` rollback-to retry, and accepted pager/WAL/B-tree current-source savepoint clusters. The new surface is the `OR ROLLBACK` transaction-cancel boundary plus retry from the restored transaction image.
