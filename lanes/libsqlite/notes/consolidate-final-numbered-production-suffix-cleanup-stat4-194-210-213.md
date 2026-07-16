# STAT4 Numbered Helper Consolidation 194/210/213

Consolidated three remaining numbered production helper stacks for proof
windows 194, 210, and 213 in
`SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan` into descriptive
canonical method names:

- Proof window 194 now uses the `DistinctResidual` helper family.
- Proof window 210 now uses the `PeerRowidWindow` helper family.
- Proof window 213 now uses the `LikeCaseFence` helper family.

The existing observable `next194`, `next210`, and `next213` result keys,
dependency strings, status strings, proof labels, and non-overlap text remain
unchanged. Direct tests and Application examples were renamed to descriptive
filenames and updated to call the canonical methods.

Verification:

- `php -l lanes/libsqlite/src/SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialDistinctResidualTest.php`
- `php -l lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialPeerRowidWindowTest.php`
- `php -l lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialLikeCaseFenceTest.php`
- `php -l lanes/libsqlite/examples/application-sqlplanner-stat4-expression-partial-distinct-residual.php`
- `php -l lanes/libsqlite/examples/application-sqlplanner-stat4-expression-partial-peer-rowid-window.php`
- `php -l lanes/libsqlite/examples/application-sqlplanner-stat4-expression-partial-like-case-fence.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialDistinctResidualTest.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialPeerRowidWindowTest.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialLikeCaseFenceTest.php` -> `3 test files, 201 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-sqlplanner-stat4-expression-partial-distinct-residual.php`
- `php lanes/libsqlite/examples/application-sqlplanner-stat4-expression-partial-peer-rowid-window.php`
- `php lanes/libsqlite/examples/application-sqlplanner-stat4-expression-partial-like-case-fence.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartial*Test.php` -> `133 test files, 7543 assertions, 0 failures`

Dependency closure: no new support component is needed; this is a production
identifier consolidation over existing STAT4 expression partial planner
behavior.
