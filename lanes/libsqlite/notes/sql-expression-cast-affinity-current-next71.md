# SQL Expression CAST Affinity Current/Next71

Status delta:

- Added SQLite declared-type CAST affinity resolution for parser-level SELECT expressions.
- CAST target strings now follow SQLite affinity rules: any `INT` maps to integer, `CHAR`/`CLOB`/`TEXT` maps to text, `BLOB`/`NONE` maps to BLOB affinity, `REAL`/`FLOA`/`DOUB` maps to real, and otherwise falls back to NUMERIC.
- `SQLiteSelectSql` now accepts multi-word and precision-suffixed CAST targets such as `UNSIGNED BIG INT`, `DOUBLE PRECISION`, `VARCHAR(20)`, and `DECIMAL(10,2)`.

Focused verification:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteSqlExpressionCastAffinityCurrentNext71Test.php
# Focused test run: 1 selected test files (root lock skipped)
# 1 test files, 60 assertions, 0 failures

php lanes/libsqlite/examples/application-select-cast-affinity-current-next71.php
# scenario: application-select-cast-affinity-current-next71

php -l lanes/libsqlite/src/SQLiteSelectExpression.php
php -l lanes/libsqlite/src/SQLiteSelectSql.php
php -l lanes/libsqlite/tests/SQLiteSqlExpressionCastAffinityCurrentNext71Test.php
php -l lanes/libsqlite/examples/application-select-cast-affinity-current-next71.php
# No syntax errors detected in changed PHP files.

git diff --check -- lanes/libsqlite
# no output
```

Dependency closure:

No new support component is needed. The slice reuses native `SQLiteSelectSql`, `SQLiteSelectExpression`, row-array predicates, grouping, ordering, and BLOB value support.

Non-overlap:

This avoids accepted numeric CAST overflow, scalar cast-affinity comparison corpus, expression ORDER BY, SELECT subquery/GROUP BY/JOIN text dispatch, JSON table cursor/source/constraint work, Unicode GLOB, VFS writer/lock/sync/rollback, WAL checkpoint/savepoint, and B-tree page-move/root-collapse/overflow clusters. The new behavior is the remaining declared-type CAST affinity mapping for current/next SQL expression execution.
