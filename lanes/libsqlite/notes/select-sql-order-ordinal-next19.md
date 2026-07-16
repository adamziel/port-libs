# SELECT SQL ordinal ORDER BY next19

## Behavior

Simple parser-level `SELECT` text now resolves integer `ORDER BY` terms as
SQLite result-column ordinals before adding hidden expression-order columns.
This covers `ORDER BY 1`, `ORDER BY 2 DESC`, collation modifiers, limits,
constant SELECTs, CTEs, joins, grouped aggregate result columns, and projected
expression aliases. Bounded wildcard ordinals are rejected because this planner
does not expand wildcard result positions until projection time.

## Evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteSelectSqlOrdinalOrderNext19Test.php`
- Result: `1 test files, 43 assertions, 0 failures`
- New focused PASS-line delta: `+43`
- `php lanes/libsqlite/examples/application-select-sql-order-ordinal.php`
- `php -l lanes/libsqlite/src/SQLiteSelectSql.php`
- `php -l lanes/libsqlite/tests/SQLiteSelectSqlOrdinalOrderNext19Test.php`
- `php -l lanes/libsqlite/examples/application-select-sql-order-ordinal.php`
- `git diff --check -- lanes/libsqlite`

## Non-Overlap

This does not repeat accepted arbitrary SQL expression `ORDER BY` hidden-column
dispatch, compound SELECT `ORDER BY` ordinal handling, grouped SELECT SQL text,
JSON table source/constraint work, VFS writer/lock/sync work, WAL savepoint or
rollback-journal application, Unicode GLOB ranges, or B-tree page move/freeblock
clusters. The change is limited to simple SELECT result-column ordinal
resolution.

## Dependency Closure

No new support component is needed. The slice reuses the existing bounded
`SQLiteSelectSql`, `SQLiteSelectQuery`, and `SQLiteSelectResult` planner/result
components.
