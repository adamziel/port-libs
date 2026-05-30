# Final Numbered Production Suffix Cleanup

Consolidated the STAT4 expression-partial duplicate sample fanout entry point
inside `SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan`:

- renamed production `materializeNext173()` to
  `materializeDuplicateSampleFanout()`;
- renamed the private `*Next173` helpers to descriptive duplicate-fanout helper
  names;
- migrated the direct focused test and WordPress smoke example to descriptive
  filenames and the canonical method name.

Observable plan metadata is intentionally preserved, including the existing
`next173*` proof keys, `stat4-expression-partial-current-source-next173-ready`
status, dependency marker, detail text, and non-overlap text. Those values feed
existing proof assertions and are not compatibility shims for production
callables.

Verification:

- `php -l lanes/libsqlite/src/SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialDuplicateSampleFanoutTest.php`
- `php -l lanes/libsqlite/examples/wordpress-sqlplanner-stat4-expression-partial-duplicate-sample-fanout.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialDuplicateSampleFanoutTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartial*Test.php`
- `php lanes/libsqlite/examples/wordpress-sqlplanner-stat4-expression-partial-duplicate-sample-fanout.php --self-test`
- `git diff --check -- lanes/libsqlite`

Dependency closure: no new support component is needed; this cleanup reuses the
existing STAT4 expression-partial planner implementation and preserves the
accepted duplicate fanout behavior.
