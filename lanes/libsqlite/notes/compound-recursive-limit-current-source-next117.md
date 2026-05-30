# compound-select-recursive-limit-current-source-next117

## Behavior

- Compound SELECT arms are now aligned by result-column position and retain the
  left-most SELECT column names, matching SQLite behavior.
- Parser-level `SQLiteSelectSql` normalizes every compound arm to the first
  arm's output columns before UNION / UNION ALL / INTERSECT / EXCEPT.
- Recursive CTE queue `LIMIT` / `OFFSET` rows can be compounded with current
  `wp_options` source rows even when the recursive arm projects `id` and the
  current-source arm projects `option_id`.

## Evidence

```text
$ php tools/run-tests.php lanes/libsqlite/tests/SQLiteCompoundRecursiveLimitCurrentSourceNext117Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 52 assertions, 0 failures
```

```text
$ php lanes/libsqlite/examples/application-compound-recursive-limit-current-source-next117.php --self-test
application-compound-recursive-limit-current-source-next117 self-test passed
```

## Non-Overlap

This avoids the accepted batch109-113 compound SELECT expression ORDER/LIMIT
cluster. That work covered exact-expression ORDER BY and final LIMIT behavior;
this slice covers the distinct SQLite rule that compound arms match by ordinal
and preserve the left-most output names, including recursive CTE queue
LIMIT/OFFSET rows compounded with current Application source rows.

## Dependency Closure

No new support component is needed. The patch reuses lane-local
`SQLiteSelectSql`, `SQLiteSelectCompound`, and recursive CTE execution; it does
not require ext/sqlite, live services, or external runners.
