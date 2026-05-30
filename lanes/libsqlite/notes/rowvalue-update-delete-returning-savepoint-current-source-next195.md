# rowvalue-update-delete-returning-savepoint-current-source-next195

Status: focused PHP behavior growth for current-source row-value UPDATE/DELETE
RETURNING savepoint execution.

This slice adds SQLite-style unary `NOT` wrapping for row-value predicates in
the bounded UPDATE/DELETE RETURNING executor. It covers `NOT ((a,b) IS
DISTINCT FROM (...))` and `NOT ((a,b) IS NOT DISTINCT FROM (...))` in WHERE
selection, RETURNING expressions, and UPDATE assignment expressions, then proves
that a rolled-back savepoint suppresses attempted RETURNING rows and retries
from the original current-source image.

Application smoke: `application-rowvalue-unary-not-distinct-savepoint-current-source-next195.php`
models a copied `wp_options` import that updates only blog-1 live URL rows and
deletes only non-blog-1 transient rows while preserving the blog-1 NULL-status
transient through `IS NOT DISTINCT FROM` semantics.

Verification:

```sh
php -l lanes/libsqlite/src/SQLiteUpdateDeleteReturningSql.php
php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext195Test.php
php -l lanes/libsqlite/examples/application-rowvalue-unary-not-distinct-savepoint-current-source-next195.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext195Test.php
php lanes/libsqlite/examples/application-rowvalue-unary-not-distinct-savepoint-current-source-next195.php --self-test
git diff --check -- lanes/libsqlite
```

Expected focused delta: `+51` PASS lines, moving `phpPass` from `93683` to
`93734`. Mapped upstream coverage remains `618 / 1589`; this is executor
behavior over already mapped row-value UPDATE/DELETE RETURNING inventory.

Non-overlap: avoids accepted row-value `IS` / `IS NOT`, `IS DISTINCT FROM`
without unary wrapping, nullable equality/inequality, row-value `IN` / empty
`IN`, BETWEEN / NOT BETWEEN, assignment parsing, OR ROLLBACK conflict handling,
and accepted trigger/WAL/pager/B-tree/JSON surfaces. The new surface is unary
`NOT` around a row-value distinct predicate across selection, projection,
assignment, rollback suppression, and retry.

Dependency closure: no new support component is needed. This reuses the native
PHP UPDATE/DELETE RETURNING executor and existing savepoint rollback/retry
planner.

Next task: continue with a non-overlapping SQL executor/planner row-value gap
or rebase the queued rowvalue conflict item if the supervisor assigns it.
