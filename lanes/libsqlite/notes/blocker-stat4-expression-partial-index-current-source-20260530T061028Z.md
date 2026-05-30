# STAT4 expression partial current-source blocker

Status: fixed a current-source STAT4 expression partial-index residual mismatch.

Behavior: `SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeRepreparedPartialExpressionIndex()` already selected `sqlite_stat4` samples with the index collation, but final expression-term residual filtering used `BINARY`. A `NOCASE` partial expression index over copied `wp_options.option_name` could therefore select the correct mixed-case STAT4 sample and then drop the mixed-case row from the current-source row stream.

Patch:

- Threads the selected index collation into expression residual checks.
- Applies that collation to expression `=`, `IN`, `BETWEEN`, and range comparisons.
- Adds focused NOCASE current-source reprepare assertions and a WordPress smoke.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialReprepareTest.php` passed: `1 test files, 71 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartial*Test.php` passed: `133 test files, 7561 assertions, 0 failures`.
- `php lanes/libsqlite/examples/wordpress-sqlplanner-stat4-expression-partial-nocase-reprepare.php --self-test` passed.

Dependency closure: no new support component is needed. This reuses existing lane-local STAT4 sample parsing, expression value extraction, partial predicate implication, and current-source reprepare diagnostics.

Non-overlap: this does not touch suffix consolidation, STAT4 handoff aliases, expression ORDER BY, range-cost ranking, JSON, VFS/WAL, or B-tree behavior. The slice is the current-source residual collation check after a STAT4 expression partial-index plan has already been selected.
