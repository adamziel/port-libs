## Scope

- Consolidated the remaining private `Next430445` helper method names in
  `SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan` into descriptive
  prepared-handoff bridge final helper names.
- Preserved all observable planner metadata, including `stat4Next430445*`
  keys, dependency strings, status text, cursor opcodes, action labels,
  non-overlap text, and exception wording.

## Verification

- Focused direct test:
  `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialPreparedHandoffBridgeFinalTest.php`
- Affected domain family:
  `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartial*.php`
- Syntax:
  `php -l lanes/libsqlite/src/SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan.php`
- Whitespace:
  `git diff --check -- lanes/libsqlite`

## Dependency Closure

No new support component is needed. This is a production-helper consolidation
only and reuses the existing STAT4 expression-partial prepared handoff bridge
implementation.
