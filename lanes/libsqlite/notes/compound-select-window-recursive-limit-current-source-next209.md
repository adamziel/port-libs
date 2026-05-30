# Compound SELECT Window Recursive LIMIT Current Source Next209

This slice adds focused current-source coverage for a parser/executor path that
combines:

- a recursive CTE queue with `ORDER BY ... LIMIT ... OFFSET`;
- aggregate window output from `sum(...) OVER (...)` and `count(*) OVER (...)`;
- compound `UNION ALL`, `EXCEPT`, and final distinct `UNION` membership; and
- a final compound `ORDER BY metric, id LIMIT/OFFSET` current-source token
  fence over Application-style `wp_options` rows.

Focused verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext209Test.php
```

Expected focused movement is 71 PASS lines from the new lane-scoped test file
(`397` assertions, `0` failures).
The Application smoke is
`lanes/libsqlite/examples/application-compound-window-recursive-limit-current-source-next209.php`.

Non-overlap: avoids accepted next206 lead/nth_value INTERSECT fencing, next203
lag/last_value EXCEPT fencing, next196 ntile/first_value UNION distinct,
next192 percent_rank/cume_dist distribution windows, and non-SQL JSON/WAL/
B-tree/VFS clusters.

Dependency closure: no new support component is needed. The slice reuses native
SELECT SQL compound execution, recursive queue ORDER BY/LIMIT/OFFSET, aggregate
window frames, EXCEPT/UNION membership, and final LIMIT helpers.
