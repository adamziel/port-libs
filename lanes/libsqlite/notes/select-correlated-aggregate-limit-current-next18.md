# SELECT Correlated Aggregate LIMIT Current Next18

This slice fixes scalar subqueries used by parser-level `LIMIT` and `OFFSET`
when the subquery is a grouped aggregate and its `ORDER BY` term is not part of
the visible projection. `SQLiteSelectSql::correlatedSubqueryRows()` now strips
hidden ORDER BY columns before scalar, `IN`, and `EXISTS` consumers inspect the
subquery row width, matching the public SELECT path.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteSelectCorrelatedAggregateLimitCurrentNext18Test.php`
- `php lanes/libsqlite/examples/application-select-correlated-aggregate-limit-current.php`

The focused test adds 26 independent PASS cases and 74 assertions for grouped
aggregate scalar subqueries in `LIMIT`, `OFFSET`, comma LIMIT, constant SELECT,
compound SELECT, CTE-backed sources, HAVING predicate variants, arithmetic
composition, hidden aggregate ORDER expressions, empty grouped scalar
subqueries, and explicit two-column rejection.

Non-overlap: this does not repeat accepted correlated aggregate
GROUP/HAVING subqueries, scalar subquery predicates, comma LIMIT syntax, SQL
expression ORDER BY, grouped SELECT SQL text, JSON table source/cursor work, or
VFS/WAL/B-tree storage slices. It covers the narrower blocker where hidden
ORDER BY columns leaked from a grouped scalar subquery into LIMIT/OFFSET scalar
width checks.

Dependency closure: no new support component is needed. The slice reuses the
existing bounded `SQLiteSelectSql`, `SQLiteSelectExpression`,
`SQLiteSelectQuery`, and `SQLiteGroupedAggregate` components.
