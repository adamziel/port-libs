# Consolidate exact removed-suffix third sweep

Status: consolidation-only cleanup for the exact removed-suffix sweep.

Production cleanup:

- Confirmed the exact removed numeric production names are absent.
- Removed numbered `Next146` method/helper names from `SQLitePlannerExpressionCoveringRangeCurrentSourceNextPlan` by renaming the public entrypoint to `materialize()` and private helpers to stable descriptive names.
- Migrated the direct focused test and WordPress smoke caller to the canonical unsuffixed entrypoint.

Verification:

- `php -l lanes/libsqlite/src/SQLitePlannerExpressionCoveringRangeCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLitePlannerExpressionCoveringRangeCurrentSourceNext146Test.php`
- `php -l lanes/libsqlite/examples/wordpress-expression-covering-range-current-source-next146.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerExpressionCoveringRangeCurrentSourceNext146Test.php`
- `php lanes/libsqlite/examples/wordpress-expression-covering-range-current-source-next146.php --self-test`
- `git diff --check -- lanes/libsqlite`

Dependency closure: no new support component needed; this is a production-name consolidation over existing expression covering range behavior.
