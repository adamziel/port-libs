# SELECT LIMIT/OFFSET Current Source Resolve Next11

This slice adds parser-level `SELECT` SQL support for scalar subqueries inside
`LIMIT` and `OFFSET` expressions. The resolver now carries the current SELECT
source table map into LIMIT/OFFSET expression parsing, so subqueries can read
ordinary tables, CTEs, JSON table-valued sources, constant SELECT sources, and
compound SELECT sources before final row slicing.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteSelectLimitOffsetCurrentSourceTest.php`
- `php lanes/libsqlite/examples/application-select-sql-limit-current-source.php`

The focused test adds 25 independent PASS cases and 63 assertions for scalar
subquery LIMIT, OFFSET, comma LIMIT offset/count, arithmetic composition,
constant SELECT, compound SELECT, CTE, JSON table source, first-row scalar
subquery behavior, and malformed non-numeric/multi-column LIMIT failures.

Non-overlap: this is not the accepted comma-LIMIT syntax slice, expression
ORDER BY slice, SELECT subquery predicate slice, grouped SELECT text slice, or
JSON table SELECT-source slice. It only resolves LIMIT/OFFSET scalar
expressions against current source tables.

Dependency closure: no new support component is needed. The slice reuses the
existing bounded `SQLiteSelectSql`, `SQLiteSelectExpression`, and
`SQLiteSelectQuery` components.
