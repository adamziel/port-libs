# sqlplanner-stat4-expression-partial-current-source-next239

- Behavior: adds a current-source partial-index cardinality fence on top of the accepted next236 STAT4 expression partial density-vector guard. The new guard rejects reuse when the selected current partial expression index has stale `sqlite_stat1` row estimates even though current STAT4 sample rowids and density vectors still validate.
- WordPress path: `wordpress-sqlplanner-stat4-expression-partial-current-source-next239.php` models copied `wp_options` plugin-option scans over `lower(option_name)` partial indexes after autoload/plugin rows move in the current source.
- Verification:
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext239Test.php`
  - `php lanes/libsqlite/examples/wordpress-sqlplanner-stat4-expression-partial-current-source-next239.php`
  - `php -l lanes/libsqlite/src/SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan.php`
  - `php -l lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext239Test.php`
  - `php -l lanes/libsqlite/examples/wordpress-sqlplanner-stat4-expression-partial-current-source-next239.php`
  - `git diff --check -- lanes/libsqlite`
- Dependency closure: no new support component needed; this reuses current native STAT4 expression partial planning data and bounded row-array predicate evaluation.
- Non-overlap: avoids accepted next236 density vectors, next235 vector counters, next233 sample row guards, expression ORDER BY, range-cost ranking, JSON, WAL, VFS, B-tree, trigger, UTF, and suite-runner clusters.
