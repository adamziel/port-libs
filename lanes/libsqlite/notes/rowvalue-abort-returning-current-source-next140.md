# rowvalue-abort-returning-current-source-next140

Status: focused PHP behavior growth for row-value `UPDATE ... RETURNING`
conflict handling over a copied Application `wp_options` savepoint.

This slice adds `SQLiteRowValueAbortReturningSavepointCurrentSourceNextPlan`.
It models SQLite `OR ABORT` behavior for row-value UPDATE RETURNING batches:
the failing statement is backed out to the statement image, earlier successful
statements and RETURNING streams remain visible, and the savepoint transaction
stays active unless the conflict action is `OR ROLLBACK`.

Application path:
`application-rowvalue-abort-returning-current-source-next140.php` stages copied
multisite `wp_options` rows, then hits a later unique `(blog_id, option_name)`
collision. The smoke verifies that staged rows remain current while the
aborted statement restores its attempted rows.

Verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueAbortReturningSavepointCurrentSourceNext140Test.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 58 assertions, 0 failures

php lanes/libsqlite/examples/application-rowvalue-abort-returning-current-source-next140.php --self-test
application-rowvalue-abort-returning-current-source-next140 self-test passed
```

Dashboard delta: `phpPass` moves from `60841` to `60899` for the 58 verified
PASS lines. Mapped upstream coverage is unchanged because this is a
current-source PHP behavior slice over already mapped row-value UPDATE
RETURNING/conflict surfaces, not a newly hydrated upstream inventory row.

Non-overlap: avoids accepted row-value current-source next117, savepoint
conflict next128, FAIL preservation next132, UPSERT conflict next134,
row-value UPDATE conflict next137, and IGNORE/REPLACE/ROLLBACK conflict
savepoint next138. The new behavior is the still-missing `OR ABORT`
statement rollback boundary in a multi-statement savepoint with prior
RETURNING yields preserved.

Dependency closure: no new support component is needed. The patch reuses the
existing lane-local `SQLiteUpdateDeleteReturningSql` row-value executor,
unique-conflict detector, and savepoint current-source modeling.
