# Final Numbered Production Suffix Cleanup Dynamic

Status: consolidated the STAT4 dynamic fence entry/helper names inside
`SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan` for the next230
through next235 gap-density, page-membership, counter, sample-row, histogram,
and vector-counter fences.

Scope:

- Renamed private helper methods to stable descriptive STAT4 fence names.
- Renamed the public entry points to descriptive
  `materializeCurrentSource...Fence()` methods while preserving existing
  returned keys, dependency strings, cursor modes, action labels, status
  values, and test names.
- The user-named 150 production suffix scan remains clean across source, tests,
  examples, and notes.

Verification:

- `php -l` for the changed source file, six changed direct tests, and six
  changed WordPress examples.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext230Test.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext231Test.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext232Test.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext233Test.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext234Test.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext235Test.php`
  - `6 test files, 417 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartial*Test.php`
  - `133 test files, 7543 assertions, 0 failures`
- Changed WordPress examples next230 through next235 run with `--self-test`.
- `git diff --check -- lanes/libsqlite`

Dependency closure: no new support component is needed; this is a production
helper-name consolidation that reuses the existing STAT4 expression partial
planner implementation and focused test family.
