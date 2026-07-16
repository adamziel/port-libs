# rowvalue-update-delete-returning-savepoint-current-source-next202

Status: focused PHP behavior growth for current-source row-value UPDATE/DELETE
RETURNING execution.

This slice fixes parser/executor handling for row-value predicates and
RETURNING expressions that are wrapped in an extra full-expression parenthesis
layer, such as `WHERE (((blog_id, option_name) = (1, 'siteurl')))` and
`RETURNING (((blog_id, option_name) IS NOT DISTINCT FROM (...))) AS stable`.
SQLite accepts these forms, and Application migration/import SQL generators often
add parenthesized predicate groups when composing retryable cleanup statements.

Implementation:

- `SQLiteUpdateDeleteReturningSql` now unwraps enclosing parentheses before
  splitting top-level `WHERE` groups and before evaluating predicate/RETURNING
  expressions.
- `SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext202Plan` models
  a savepoint attempt whose parenthesized UPDATE/DELETE RETURNING stream is
  rolled back, then retried from the original savepoint image.
- `application-rowvalue-parenthesized-savepoint-current-source-next202.php`
  covers copied `wp_options` cleanup SQL with parenthesized row-value equality,
  `IN (VALUES ...)`, `IS`, `IS NOT DISTINCT FROM`, and `NOT IN` expressions.

Verification:

```sh
php -l lanes/libsqlite/src/SQLiteUpdateDeleteReturningSql.php
php -l lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext202Plan.php
php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext202Test.php
php -l lanes/libsqlite/examples/application-rowvalue-parenthesized-savepoint-current-source-next202.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext202Test.php
php lanes/libsqlite/examples/application-rowvalue-parenthesized-savepoint-current-source-next202.php --self-test
```

Focused result: `1 test files, 62 assertions, 0 failures` with 62 PASS lines.

Expected dashboard delta: `phpPass` moves from `97068` to `97130` from 62 newly
passing focused PASS lines. Mapped upstream coverage remains `619 / 1589`; this
is focused current-source executor behavior over already mapped row-value
UPDATE/DELETE RETURNING inventory.

Non-overlap: avoids accepted next176 nullable row-value inequality, next188
empty row-value `IN`, next190 negated savepoint retry behavior, next193 OR FAIL
stream rollback suppression, next196 OR FAIL prefix preservation, row-value
assignment parsing, DELETE row-value `IN`, savepoint page-image/WAL/VFS
rollback clusters, and trigger RETURNING clusters. The new surface is
specifically full-expression parenthesis unwrapping before row-value WHERE
splitting and RETURNING expression evaluation.

Dependency closure: no new support component is needed. The slice reuses the
existing native PHP UPDATE/DELETE RETURNING executor and lane-local savepoint
current-source modeling.

Next task: continue with a non-overlapping row-value/planner executor gap or
defer to broader SQL executor/planner current-source work.
