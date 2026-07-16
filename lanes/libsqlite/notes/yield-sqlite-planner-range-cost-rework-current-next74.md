# SQLite Planner Range Cost Rework Current/Next74

## Behavior

- Reworks rejected sqlplan71 by keeping `choose()` / `chooseLowestCost()` on the existing single-term expression-index ranking path.
- Adds an explicit `chooseBoundedRangeCost()` / `boundedRangePlans()` entry point for AND-connected lower/upper bounds on the same expression index.
- The bounded path reports STAT4 current/next evidence for the matched range window, covering metadata, ORDER BY compatibility, and residual predicate guards without changing legacy planner ordering.

## Focused Evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerRangeCostReworkCurrentNext74Test.php`
- Result: `1 test files, 51 assertions, 0 failures`
- PASS-line delta: `+51`

## Regression Evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php lanes/libsqlite/tests/SQLiteIndexPartialCoveringCorpusTest.php lanes/libsqlite/tests/SQLitePlannerExpressionPartialIndexCurrentNext30Test.php lanes/libsqlite/tests/SQLitePlannerStat4OrPartialExpressionCurrentNext53Test.php lanes/libsqlite/tests/SQLitePlannerStat4PartialExpressionOrderCurrentNext49Test.php`
- Result: `5 test files, 10144 assertions, 0 failures`
- This directly covers the five files named in the rejected sqlplan71 rework note.

## Application Smoke

- `php lanes/libsqlite/examples/application-expression-range-cost-current-next74.php`
- Covers copied `wp_options` `lower(option_name) >= 'plugin_' AND lower(option_name) < 'theme_'` scans with `autoload = 'yes'`, STAT4 bounded current/next evidence, covering metadata, and stable legacy-plan separation.

## Non-Overlap

Avoids accepted expression-index range-cost ranking by making bounded range planning opt-in. Also avoids expression ORDER BY, JSON table source/constraint work, VFS/WAL/B-tree accepted clusters, and batch68/69 accepted or queued surfaces.

## Dependency Closure

No new support component is needed. This reuses existing native PHP expression-index metadata, partial-index predicate proof, and STAT4 sample helpers.
