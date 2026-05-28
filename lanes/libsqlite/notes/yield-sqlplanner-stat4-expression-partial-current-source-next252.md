# SQLite planner STAT4 expression partial current-source next252

Status: focused PHP behavior growth for `sqlplanner-stat4-expression-partial-current-source-next252`.

Behavior: adds `SQLitePlannerStat4ExpressionPartialCurrentSourceNext252Plan`, an additive current-source fence for descending partial expression-index STAT4 plans. It reuses the accepted next249 duplicate peer-count fence and proves that ascending STAT4 samples are reversed into the same descending page anchors selected from the current qualified rowset before a prepared page is reused.

WordPress path: `wordpress-sqlplanner-stat4-expression-partial-current-source-next252.php` models copied `wp_options` plugin-admin pagination over a descending partial `lower(option_name)` covering index, where stale sample order or missing upper anchors must force current-source reprepare.

Focused verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext252Test.php`
  - `1 test files, 67 assertions, 0 failures`
- `php lanes/libsqlite/examples/wordpress-sqlplanner-stat4-expression-partial-current-source-next252.php --self-test`
  - `wordpress sqlplanner stat4 expression partial current-source next252: stat4-expression-partial-current-source-next252-ready anchors=[60,30,50,20] signature=...`

Dependency closure: no new support component needed; this composes existing lane-local STAT4 expression partial rowsets, current-source fences, and cursor-program diagnostics.

Non-overlap: avoids accepted next249 duplicate peer-count validation, next245 sample-row anchors, next231 page membership, expression ORDER BY, range-cost ranking, JSON, WAL, VFS, B-tree, trigger/FK, UTF/collation, and suite-runner clusters. The new surface is descending STAT4 scan-direction page-anchor validation for a partial expression-index plan.
