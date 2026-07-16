# Source-neutral STAT4 peer key-field cleanup

- Session: `port-dev-sqlite-yield-dyn-neutral-stat4-20260601T235444Z`.
- Micro-slice: `source-neutral-src-planner-stat4-key-fields-dynamic-20260601T235444Z-0`.
- Scope: neutralized the directly coupled STAT4 peer-cardinality and selectivity fixtures/examples from `wp_options`/`option_*`/`blog_id`/`autoload` language to generic `app_settings`/`key_*`/`tenant_id`/`load_policy` terms. The production helper source was already neutral on this base, so this slice hardens the source-neutral guard to cover the newer peer-cardinality and selectivity helper methods and proves their dynamic key-field handling with generic fixtures.
- Behavior preserved: peer-cardinality/selectivity fences still admit the same row windows, peer counts, STAT4 estimates, stale-counter blockers, and cursor opcodes.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteSourceNeutralPlannerStat4KeyFieldsDynamicTest.php && php -l lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialPeerCardinalityTest.php && php -l lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialSelectivityTest.php && php -l lanes/libsqlite/examples/application-sqlplanner-stat4-expression-partial-peer-cardinality.php && php -l lanes/libsqlite/examples/application-sqlplanner-stat4-expression-partial-selectivity.php` -> no syntax errors.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteSourceNeutralPlannerStat4KeyFieldsDynamicTest.php` -> 1 file, 61 assertions, 0 failures.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialPeerCardinalityTest.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialSelectivityTest.php` -> 2 files, 151 assertions, 0 failures.
- `php lanes/libsqlite/examples/application-sqlplanner-stat4-expression-partial-peer-cardinality.php --self-test && php lanes/libsqlite/examples/application-sqlplanner-stat4-expression-partial-selectivity.php --self-test` -> both self-tests passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartial*Test.php lanes/libsqlite/tests/SQLiteSourceNeutralPlannerStat4KeyFieldsDynamicTest.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php` -> 136 files, 7666 assertions, 0 failures.

Counter movement: source-neutral cleanup only; no `phpPass` or mapped-coverage movement claimed.

Dependency closure: no new support component needed; this reuses the existing STAT4 expression-partial planner helpers and source-neutral dynamic key-field metadata.
