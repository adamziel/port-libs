# JSON aggregate DISTINCT ORDER window current-source next90

## Behavior

This slice adds parser-level support for multi-term aggregate `ORDER BY` inside JSON aggregate window frames:

```sql
json_group_array(DISTINCT option_name ORDER BY rank DESC, tie ASC)
  FILTER (WHERE enabled)
  OVER (PARTITION BY autoload ORDER BY option_id ROWS BETWEEN CURRENT ROW AND 3 FOLLOWING)
```

The implementation keeps the aggregate input sorter independent from the window frame sorter, applies `FILTER` before DISTINCT admission, preserves stable row-position tie-breaking after all aggregate order terms compare equal, and supports JSONB output dispatch for the same multi-term order path.

## Evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonAggregateDistinctOrderWindowCurrentSourceNext90Test.php`
  - `1 test files, 58 assertions, 0 failures`
  - `57` focused PASS lines
- `php lanes/libsqlite/examples/application-json-aggregate-distinct-order-window-current-source-next90.php`
  - `application-json-aggregate-distinct-order-window-current-source-next90 self-test passed`

## Non-overlap

This does not repeat accepted batch88 JSON aggregate order/window coverage. Batch88 covered single aggregate-order terms for DISTINCT JSON array windows. This slice covers the narrower missing SQLite behavior where the aggregate sorter has multiple terms with mixed directions while the window frame keeps its own current-source order.

## Dependency closure

No new support component is needed. The patch reuses the existing native PHP `SQLiteSelectSql`, `SQLiteSelectQuery`, `SQLiteSelectExpression`, `SQLiteSelectPredicate`, and JSON aggregate helpers; no ext/sqlite, upstream binary, network service, or new dependency is required.

## Next

Next JSON aggregate work should move to object aggregate parser-level windows or malformed JSONB planner edges only if it avoids the already accepted object aggregate/window, hidden/visible constraint, and JSON table source/cursor clusters.
