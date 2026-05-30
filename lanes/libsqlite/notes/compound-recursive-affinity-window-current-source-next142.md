# compound-recursive-affinity-window-current-source-next142

## Behavior

Adds a current-source/next-source diagnostic for parser-level compound SELECT
execution where:

- a `WITH RECURSIVE` CTE uses `UNION` distinctness to suppress numeric-affinity
  duplicates such as `1` and `1.0`,
- each compound arm evaluates its window function before the outer compound
  `UNION` set operation,
- the final compound row names and `ORDER BY` terms come from the left-most arm,
- next-source Application option rows change the recursive trace and compound
  output without requiring ext/sqlite.

This intentionally avoids the accepted next137/next139 final LIMIT and
recursive queue-boundary slices. The next142 coverage keeps the tail unbounded
and focuses on set identity, window-before-compound order, left-column naming,
and current/next source deltas.

## Evidence

Focused command:

```bash
php tools/run-tests.php lanes/libsqlite/tests/SQLiteCompoundRecursiveAffinityWindowCurrentSourceNext142Test.php
```

Result:

```text
1 test files, 279 assertions, 0 failures
```

Application smoke:

```bash
php lanes/libsqlite/examples/application-compound-recursive-affinity-window-current-source-next142.php --self-test
```

Result:

```text
application-compound-recursive-affinity-window-current-source-next142 self-test passed
```

## Dependency Closure

No new support component is needed. The slice reuses the native PHP
`SQLiteSelectSql` recursive CTE, compound SELECT, window, and affinity behavior
already present in the libsqlite lane.

## Non-Overlap

Avoided accepted compound SELECT row composition, next137 final LIMIT/window
affinity, next139 recursive LIMIT/window, JSON table cursor/source wiring,
SQL expression `ORDER BY`, and current WAL/B-tree/VFS accepted clusters.
