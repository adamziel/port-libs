# rowvalue-update-delete-returning-savepoint-current-source-next204

Status: focused PHP behavior growth for current-source row-value UPDATE/DELETE
RETURNING execution.

This slice adds `SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext204Plan`
for SQLite `UPDATE OR ROLLBACK` conflicts inside an active savepoint. The
bounded executor models the transaction-level rollback boundary:

- outer UPDATE RETURNING rows and savepoint UPDATE RETURNING rows are produced
  before the conflict but suppressed by the transaction rollback;
- the conflicting row-value UPDATE uses the existing unique-conflict path and
  reports the `OR ROLLBACK` failure;
- the retry UPDATE/DELETE sequence reads the original transaction image, not
  the outer or savepoint current source;
- the final current/next source reflects only the retry statements.

WordPress smoke:

`lanes/libsqlite/examples/wordpress-rowvalue-rollback-savepoint-current-source-next204.php`
models a copied `wp_options` migration where a network option rewrite conflicts
with an existing `(blog_id, option_name)` key and rolls back both outer and
savepoint changes before retrying from the original source.

Focused verification:

```bash
php -l lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext204Plan.php
php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext204Test.php
php -l lanes/libsqlite/examples/wordpress-rowvalue-rollback-savepoint-current-source-next204.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext204Test.php
php lanes/libsqlite/examples/wordpress-rowvalue-rollback-savepoint-current-source-next204.php --self-test
git diff --check -- lanes/libsqlite
```

Non-overlap:

Avoids accepted next196 OR FAIL prefix preservation, next200 OR ABORT statement
rollback, next203 OR IGNORE/OR REPLACE conflict handling, next202 parenthesized
row-value predicates, and batch186 next202 parenthesized UPDATE/DELETE RETURNING
savepoint behavior. The new surface is specifically transaction-level
`OR ROLLBACK` invalidation of an active savepoint and RETURNING-stream
suppression for row-value UPDATE/DELETE retries.

Dependency closure:

No new support component is needed. The slice reuses the existing native PHP
UPDATE/DELETE RETURNING executor, row-value predicate/assignment parsing,
unique-conflict metadata, and lane-local savepoint/current-source plan shape.

Next task:

Continue with a non-overlapping SQL executor/planner or row-value behavior gap,
preferably one that increases focused assertions or removes a current-source
runner blocker without repeating accepted row-value conflict actions.
