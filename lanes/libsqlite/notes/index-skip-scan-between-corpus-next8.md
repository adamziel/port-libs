# SQLite index skip-scan BETWEEN corpus next8

This slice adds bounded PHP coverage for SQLite-style skip-scan over a
composite index where the left-most column is unconstrained and a later
indexed column has BETWEEN/range bounds. It deliberately avoids the accepted
prefix range, expression-index cost, SQL expression ORDER BY, Unicode GLOB,
and B-tree/VFS clusters.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteIndexSkipScanBetweenCorpusTest.php`
  passed with `1 test files, 63 assertions, 0 failures` and 56 PASS lines.
- `php lanes/libsqlite/examples/application-index-skip-scan-between.php`
  passed and reported three distinct autoload-prefix seek loops over copied
  `wp_options` rows.
- `php -l lanes/libsqlite/src/SQLiteIndexSkipScanPlan.php` passed.
- `php -l lanes/libsqlite/tests/SQLiteIndexSkipScanBetweenCorpusTest.php`
  passed.
- `php -l lanes/libsqlite/examples/application-index-skip-scan-between.php`
  passed.
- `git diff --check -- lanes/libsqlite` passed.

Dependency closure: no new support component is needed. The slice reuses the
lane-local PHP test runner and models the planner/executor behavior with
copied `wp_options` rows instead of requiring `ext/sqlite` or a live database.
