# Compound INTERSECT Window Recursive LIMIT Current Source Next164

This slice covers a disjoint compound SELECT edge from the accepted next148,
next159, and next161 work: a recursive CTE queue with `ORDER BY 3 DESC LIMIT`
feeds a windowed left arm, then `INTERSECT` compares it with a windowed
`wp_options` arm before the final `ORDER BY ... LIMIT ... OFFSET` yield
boundary.

Focused behavior:

- recursive queue ordering and LIMIT exhaustion happen before window values are
  computed for the compound arm;
- both sides of `INTERSECT` evaluate `row_number()` before set comparison;
- final LIMIT/OFFSET is applied after the intersected rowset, so a new
  high-weight option shifts the yielded Application option boundary;
- diagnostics expose changed matched labels, yield boundary rows, recursive
  trace counts, and current/next replan reasons.

Non-overlap:

- avoids accepted next148 chained `EXCEPT`, next159 `UNION ALL` comma-LIMIT,
  next161 `EXCEPT` recursive/window LIMIT, grouped SELECT text, JSON table,
  WAL, VFS, B-tree, PRAGMA, trigger, and encoding clusters;
- no new support component is needed because the patch reuses lane-local
  `SQLiteSelectSql` recursive CTE, window, compound `INTERSECT`, and
  LIMIT/OFFSET execution.

Verification:

- focused test: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteCompoundIntersectWindowRecursiveLimitCurrentSourceNext164Test.php`
- smoke: `php lanes/libsqlite/examples/application-compound-intersect-window-recursive-limit.php`
