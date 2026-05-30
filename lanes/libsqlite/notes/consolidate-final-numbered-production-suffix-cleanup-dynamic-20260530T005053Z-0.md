# Final Numbered Production Suffix Cleanup Dynamic

Status: consolidated the private STAT4 vector-counter helper names inside
`SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan` for the next235
vector-counter fence.

Scope:

- Renamed private helper methods such as `indexByNameNext235()`,
  `vectorCounterFenceNext235()`, `compareVectorNext235()`, and
  `signatureNext235()` to stable descriptive STAT4 vector-counter names.
- Preserved the public `materializeNext235()` entry point and all existing
  returned keys, dependency strings, cursor modes, action labels, status
  values, and test names.
- The user-named 150 production suffix scan remains clean across source, tests,
  examples, and notes.

Verification:

- `php -l lanes/libsqlite/src/SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext235Test.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext238Test.php`
  - `2 test files, 143 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartial*Test.php`
  - `133 test files, 7537 assertions, 0 failures`

Dependency closure: no new support component is needed; this is a production
helper-name consolidation that reuses the existing STAT4 expression partial
planner implementation and focused test family.
