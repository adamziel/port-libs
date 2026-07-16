# SQLite planner partial expression skip-scan current source next129

## Behavior

This slice adds bounded current-source planner coverage for partial skip-scan
indexes whose range key is a materialized expression, such as
`lower(option_name)`, while the skipped leading column remains unconstrained.
The planner materializes the expression key into a cursor column, rewrites
matching query and ORDER BY expression terms to that key, and fences prepared
plan reuse on schema cookie, STAT4 generation, source rows, STAT4 samples,
expression text, and expression cursor-column signature.

## Evidence

- Focused test: `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerPartialExpressionSkipScanCurrentSourceNext129Test.php`
  passed with `1 test files, 64 assertions, 0 failures`.
- Application smoke: `php lanes/libsqlite/examples/application-planner-partial-expression-skipscan-current-source-next129.php`
  passed and emitted a current-source reprepare summary with rowids
  `[2,3,7,11]`.
- Syntax checks and `git diff --check -- lanes/libsqlite` were run for this
  lane patch.

## Non-Overlap

This avoids accepted expression-index range costs, SQL expression `ORDER BY`,
STAT4 partial expression covering, next125/next127 raw-column partial
covering skip-scan current-source fences, JSON table planner/source/cursor
work, VFS/WAL/B-tree durability clusters, and encoding GLOB work. The new
behavior is the expression-key materialization layer for partial skip-scan
current-source selection.

## Dependency Closure

No new support component is needed. The patch reuses native PHP skip-scan,
partial predicate proof, STAT4 sample diagnostics, current-source fences, and
bounded SQL expression-key materialization.
