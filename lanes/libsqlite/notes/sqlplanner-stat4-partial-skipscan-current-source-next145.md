# sqlplanner-stat4-partial-skipscan-current-source-next145

## Behavior

Adds `SQLitePlannerStat4PartialSkipScanCurrentSourceNextPlan`, a bounded planner materialization for stale prepared statements whose current source still admits a STAT4-backed partial expression skip-scan. The slice records per-prefix seek programs, current-source covering payload rows, STAT4 current/next suffix pairs, and current-source fence signatures for WordPress-style `wp_options` partial indexes on `lower(option_name)`.

## Evidence

- `php -l lanes/libsqlite/src/SQLitePlannerStat4PartialSkipScanCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLitePlannerStat4PartialSkipScanCurrentSourceNext145Test.php`
- `php -l lanes/libsqlite/examples/wordpress-planner-stat4-partial-skipscan-current-source-next145.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4PartialSkipScanCurrentSourceNext145Test.php`
  - `1 test files, 65 assertions, 0 failures`
  - `65` PASS lines
- `php lanes/libsqlite/examples/wordpress-planner-stat4-partial-skipscan-current-source-next145.php`
  - status `stat4-partial-skipscan-current-source-next145-ready`
  - selected source `current`
  - rowids `[1,2,11,4,5,7,10,9]`

## Non-Overlap

Avoids accepted expression `ORDER BY`, expression-index range-cost, JSON, VFS, WAL, and B-tree clusters. It also avoids the next141 source-fence-only planner surface by adding current-source per-prefix cursor programs and covering payload evidence rather than only rejecting stale next-source signatures.

## Dependency Closure

No new support component is needed. The slice reuses native partial-index proof, STAT4 skip-scan estimates, expression-key materialization, and current-source reprepare fences already present in the libsqlite lane.
