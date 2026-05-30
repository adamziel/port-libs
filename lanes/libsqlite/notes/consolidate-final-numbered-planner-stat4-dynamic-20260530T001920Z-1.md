# Planner STAT4 Dynamic Numbered Consolidation

Consolidated the remaining direct numbered production entry points for the
STAT4 expression-partial density-vector and trailing-payload dynamic fences:

- `materializeNext236()` is now
  `materializeCurrentSourceStat4DensityVectorValidation()`.
- `materializeNext237()` is now
  `materializeCurrentSourceTrailingPayloadValidation()`.

Observable proof names, status values, dependency strings, action labels,
cursor opcodes, cursor modes, and returned `next236` / `next237` metadata keys
are preserved. Direct tests and WordPress smokes now call the descriptive
canonical entry points.

Verification:

- `php -l lanes/libsqlite/src/SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext236Test.php`
- `php -l lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext237Test.php`
- `php -l lanes/libsqlite/examples/wordpress-sqlplanner-stat4-expression-partial-current-source-next236.php`
- `php -l lanes/libsqlite/examples/wordpress-sqlplanner-stat4-expression-partial-current-source-next237.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext236Test.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext237Test.php`
  - `2 test files, 155 assertions, 0 failures`
- `php lanes/libsqlite/examples/wordpress-sqlplanner-stat4-expression-partial-current-source-next236.php --self-test`
  - emitted `stat4-expression-partial-current-source-next236-ready`
- `php lanes/libsqlite/examples/wordpress-sqlplanner-stat4-expression-partial-current-source-next237.php --self-test`
  - emitted `stat4-expression-partial-current-source-next237-ready`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext*Test.php`
  - `82 test files, 5031 assertions, 0 failures`

Dependency closure: no new support component is needed; this only renames
production entry points/helpers inside the existing canonical STAT4 planner
class and preserves the current-source STAT4 proof surface.

Root harness: not run - isolated micro-slice.
