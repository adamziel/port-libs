# SQL planner STAT4 expression partial range current-source next165

Status: focused behavior growth for `sqlplanner-stat4-expression-partial-current-source-next165`.

Behavior:

- Extends the STAT4 expression partial planner to admit one-sided expression
  range constraints (`>`, `>=`, `<`, `<=`) and to prove partial-index column
  predicates with compatible range terms.
- Adds a next165 wrapper that records range constraint operators, partial range
  predicate operators, dependency closure, and non-overlap metadata separately
  from accepted next154 equality/IN/BETWEEN coverage.
- Application smoke models copied `wp_options` import diagnostics where
  `lower(option_name) >= 'plugin_cache'` and `updated_at` bounds prove a recent
  partial expression index after current-source STAT4 refresh.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext165Test.php`
  - `1 test files, 57 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-sqlplanner-stat4-expression-partial-range-current-source-next165.php --self-test`
  - `application-sqlplanner-stat4-expression-partial-range-current-source-next165 self-test passed`

Dependency closure:

- No new support component needed; this composes existing lane-local STAT4
  expression row streams, current-source fences, and partial predicate
  implication with bounded one-sided range comparison logic.

Non-overlap:

- Avoids accepted next154 equality/IN/BETWEEN row streams, expression partial
  covering next148, expression ORDER BY, expression-index range-cost ranking,
  JSON table generated/hidden/visible constraint work, VFS/WAL durability,
  B-tree pointer-map/freeblock clusters, and suite-runner evidence work.
