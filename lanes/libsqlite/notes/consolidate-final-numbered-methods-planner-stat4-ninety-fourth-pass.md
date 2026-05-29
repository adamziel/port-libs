## Scope

- Consolidated the private `Next167` helper method cluster in
  `SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan` into descriptive
  STAT4 expression-partial current-source helper names.
- Preserved observable planner metadata, including the public
  `materializeNext167()` entry point, `next167*` result keys, dependency
  strings, status text, cursor opcodes, detail text, non-overlap text, and
  exception wording.

## Verification

- Direct focused test:
  `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext167Test.php`
- Direct WordPress smoke:
  `php lanes/libsqlite/examples/wordpress-sqlplanner-stat4-expression-partial-current-source-next167.php --self-test`
- Affected domain family:
  `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartial*.php`
- Syntax:
  `php -l lanes/libsqlite/src/SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan.php`
- Whitespace:
  `git diff --check -- lanes/libsqlite`

## Dependency Closure

No new support component is needed. This is a production-helper consolidation
only and reuses the existing STAT4 expression-partial current-source planning
implementation.
