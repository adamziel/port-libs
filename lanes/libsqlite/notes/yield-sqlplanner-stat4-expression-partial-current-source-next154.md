# SQL Planner STAT4 Expression Partial Current Source Next154

This slice adds a lane-local planner diagnostic for stale prepared statements that
use a partial expression index with STAT4 samples over copied `wp_options` rows.

Evidence target:

- `SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan` fences schema
  cookie, STAT4 generation, index metadata, partial predicate terms, current
  source rows, and selected STAT4 row stream before returning index cursor
  diagnostics.
- The focused test covers stale current-source reprepare, equality/IN/BETWEEN
  expression constraints, partial-predicate proof, non-covering table lookup,
  covering-index elision, STAT4 current/next sample pairs, and invalid-source
  guards.
- The WordPress smoke exercises a copied `wp_options` plugin-option lookup where
  current ANALYZE/STAT4 data changes after the prepared source was built.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext154Test.php`
  passed: `1 test files, 64 assertions, 0 failures`.
- `php lanes/libsqlite/examples/wordpress-sqlplanner-stat4-expression-partial-current-source-next154.php`
  passed and selected the current-source partial expression index with rowids
  `[2, 3]`.
- PHP lint passed for the changed source, test, and example files.

Dependency closure:

- No new support component needed; this composes existing lane-local expression
  terms, partial predicate implication, STAT4 sample diagnostics, and
  current-source row materialization.

Non-overlap:

- Avoids accepted STAT4 collation boundary, expression partial covering
  next148, skip-scan current-source next141, expression ORDER BY, range-cost,
  JSON, VFS/WAL, and B-tree clusters.
