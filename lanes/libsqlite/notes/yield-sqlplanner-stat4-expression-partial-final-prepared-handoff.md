# SQLite planner STAT4 expression partial current source final prepared handoff

- Extends `SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan` without adding a numbered duplicate class.
- Carries the prepared `next974-989` current-source STAT4 handoff into `final prepared handoff` only when the projected current row image still matches the prior handoff window.
- Keeps the slice-specific dependency marker, cursor opcode, and handoff signature isolated to `final prepared handoff`.
- Consolidates the terminal production entry/helper names from generated numeric names to stable `FinalPreparedHandoff` names, and migrates the direct test/example filenames and calls.

Verification:

- `php -l lanes/libsqlite/src/SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialFinalPreparedHandoffTest.php`
- `php -l lanes/libsqlite/examples/wordpress-sqlplanner-stat4-expression-partial-final-prepared-handoff.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialFinalPreparedHandoffTest.php` => `1 test files, 39 assertions, 0 failures`
- `php lanes/libsqlite/examples/wordpress-sqlplanner-stat4-expression-partial-final-prepared-handoff.php --self-test`
