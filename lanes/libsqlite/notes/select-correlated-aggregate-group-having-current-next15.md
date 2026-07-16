# SELECT Correlated Aggregate GROUP/HAVING Current Next15

## Scope

This slice enables parser-level correlated subqueries that contain `GROUP BY`
and aggregate `HAVING` predicates. The existing subquery executor already
merges each outer row into the subquery row source before predicate execution;
the remaining blocker was an explicit rejection of grouped subquery plans.

## Evidence

- Focused test file: `lanes/libsqlite/tests/SQLiteSelectCorrelatedAggregateGroupHavingCurrentNext15Test.php`
- Application smoke: `lanes/libsqlite/examples/application-select-correlated-aggregate-group-having.php`
- Expected `phpPass` movement: `+31` verified focused PASS lines.

## Non-Overlap

This does not repeat accepted top-level `GROUP BY` / `HAVING`, parser-level
subqueries, expression `ORDER BY`, JSON table sources/constraints, VFS, WAL, or
B-tree storage slices. It covers the previously rejected intersection:
correlated scalar/EXISTS/IN subqueries whose inner SELECT uses aggregate
grouping and HAVING while referencing the current outer row.

## Dependency Closure

No new support component is needed. The patch reuses the existing bounded
`SQLiteSelectSql`, `SQLiteSelectQuery`, `SQLiteGroupedAggregate`, and
`SQLiteSelectPredicate` components.
