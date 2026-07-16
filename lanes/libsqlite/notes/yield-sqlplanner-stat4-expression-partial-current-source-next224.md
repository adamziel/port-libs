# sqlplanner-stat4-expression-partial-current-source-next224

Status: focused PHP behavior growth for current-source STAT4 expression partial-index planning.

This slice adds `SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan`. It composes the accepted next218 expression-payload covering fence, then validates that the selected page of matched `lower(option_name)` rows resolves to current `sqlite_stat4` samples in the scan order actually reused by the descending partial expression index. The new fence blocks reuse when a matched expression key is missing from current samples or when current sample order no longer matches the selected stream.

Application smoke: `application-sqlplanner-stat4-expression-partial-current-source-next224.php` models a copied `wp_options` plugin scan where `plugin_seo`, `Plugin_Mail`, and duplicate `plugin_forms` rows can reuse the current partial expression index only after current STAT4 sample ordinals prove the page order and duplicate peers.

Verification:

- `php -l lanes/libsqlite/src/SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext224Test.php`
- `php -l lanes/libsqlite/examples/application-sqlplanner-stat4-expression-partial-current-source-next224.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext224Test.php`
  - `1 test files, 69 assertions, 0 failures`
  - 69 `PASS planner stat4 expression partial current source next224 ...` lines
- `php lanes/libsqlite/examples/application-sqlplanner-stat4-expression-partial-current-source-next224.php --self-test`
  - `application-sqlplanner-stat4-expression-partial-current-source-next224 self-test passed`

Dependency closure: no new support component needed. This reuses lane-local current-source STAT4 expression partial planner fixtures and adds bounded PHP validation over existing current `stat4Samples`.

Non-overlap: avoids accepted next218 expression payload coverage, grouped LIKE/OR admission, rowid alias, duplicate fanout payloads, expression ORDER BY, range-cost ranking, JSON, WAL, VFS, B-tree, trigger, and UTF clusters. The new behavior is current `sqlite_stat4` sample-order proof for the already selected expression partial index page.
