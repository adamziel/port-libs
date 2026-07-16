# rowvalue-update-delete-returning-savepoint-current-source-next216

Status: focused PHP behavior growth for current-source row-value UPDATE/DELETE RETURNING execution.

This slice adds native support for `SELECT DISTINCT` tuple sources inside row-value `IN` subqueries used by UPDATE/DELETE `WHERE` clauses and RETURNING expressions. Duplicate staging rows in copied Application option metadata now materialize to one row-value tuple before mutation selection, so savepoint rollback and retry execution do not over-count duplicate source tuples.

Application smoke: `application-rowvalue-distinct-subquery-savepoint-current-source-next216.php` covers duplicate `wp_optionmeta` migration rows driving a deduplicated row-value UPDATE plus a deduplicated network DELETE after rollback-to-savepoint.

Verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext216Test.php
php lanes/libsqlite/examples/application-rowvalue-distinct-subquery-savepoint-current-source-next216.php --self-test
php -l lanes/libsqlite/src/SQLiteUpdateDeleteReturningSql.php
php -l lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan.php
php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext216Test.php
php -l lanes/libsqlite/examples/application-rowvalue-distinct-subquery-savepoint-current-source-next216.php
git diff --check -- lanes/libsqlite
```

Expected dashboard delta: +50 focused PASS lines in the new next216 test file. `phpPass` is updated from `106077` to `106127`. Mapped upstream coverage remains `624 / 1589`; this is focused executor behavior over existing row-value UPDATE/DELETE RETURNING inventory rather than a fresh upstream denominator row.

Non-overlap: avoids accepted next212 plain row-value subqueries, next213 ORDER/LIMIT subqueries, next210 OR IGNORE conflict rollback, next176 NULL inequality, next213 row-value ORDER/LIMIT savepoint behavior, trigger RETURNING, WAL/VFS, JSON, planner, encoding, and B-tree clusters. The new surface is specifically SELECT DISTINCT tuple de-duplication for row-value UPDATE/DELETE RETURNING subqueries.

Dependency closure: no new support component is needed. The slice reuses native PHP UPDATE/DELETE RETURNING execution, row-value tuple materialization, and savepoint current-source retry images.

Next task: continue with a non-overlapping SQL executor/planner gap or accepted-HEAD suite blocker; do not repeat row-value DISTINCT subquery handling.
