# Compound Materialized CTE Current Next15

## Behavior

- `SQLiteSelectSql` accepts SQLite CTE materialization hints after `AS`:
  `AS MATERIALIZED (...)` and `AS NOT MATERIALIZED (...)`.
- The bounded executor keeps its existing row-array materialization model, so
  hints are syntax-compatible no-ops while values, SELECT, compound, and
  recursive CTE bodies remain visible to current-source compound SELECT arms.
- Covered compound consumers include `UNION`, `UNION ALL`, `INTERSECT`,
  `EXCEPT`, predicate subqueries, scalar subquery `LIMIT`, final `ORDER BY`,
  and `LIMIT/OFFSET` over copied `wp_options` rows.

## Focused Evidence

- Before implementation, `WITH seed(x) AS MATERIALIZED (VALUES (1)) SELECT x FROM seed`
  failed with `SQLite SELECT SQL CTE seed needs a parenthesized SELECT`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteCompoundMaterializedCteCurrentNext15Test.php`
  passed: `1 test files, 26 assertions, 0 failures`.
- Expected dashboard movement from this clean worktree: `phpPass` +26
  (`4362` to `4388` in lane status). Mapped upstream denominator unchanged.

## Application Smoke

- `lanes/libsqlite/examples/application-compound-materialized-cte.php` previews
  copied `wp_options` rows flowing through a materialized CTE and compound
  `UNION` arms without requiring `ext/sqlite`.

## Non-Overlap

Avoids accepted parser-level SELECT SQL text dispatch, JOIN text dispatch,
GROUP BY/HAVING text, expression `ORDER BY`, comma `LIMIT`, correlated
`EXISTS`/`IN` subqueries, JSON table SELECT sources/hidden/visible constraints,
WAL/VFS/B-tree accepted storage clusters, and recursive CTE current-source
behavior except for the newly supported materialization hint syntax.

## Dependency Closure

No new support component is needed. This reuses the existing bounded
`SQLiteSelectSql` parser/executor, `SQLiteSelectCompound`, and row-array CTE
materialization path.
