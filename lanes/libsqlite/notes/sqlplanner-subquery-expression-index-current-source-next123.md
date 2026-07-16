# sqlplanner-subquery-expression-index-current-source-next123

This slice adds a bounded current-source planner plan for `IN (SELECT ...)`
keys that already represent expression-index terms, for example Application
option imports that probe `lower(option_name)` through a staged subquery.

Behavior covered:

- reprepare when the prepared source and current source differ by schema cookie,
  STAT4 generation, or index signature;
- expression-index matching for subquery keys with affinity and collation
  normalization;
- duplicate and SQL NULL handling before index seeks;
- partial-predicate admission for `lower(option_name) >= 'plugin_'`;
- covering vs deferred table lookup cursor tape;
- integer-affinity expression keys such as `length(option_name)`.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerSubqueryExpressionIndexCurrentSourceNext123Test.php`
  - `1 test files, 63 assertions, 0 failures`
  - 63 focused PASS lines
- `php lanes/libsqlite/examples/application-subquery-expression-index-current-source-next123.php`
  - status `subquery-expression-index-current-source-ready`
  - selected source `current`
  - index `idx_wp_options_lower_active_name`
  - values `plugin_cache`, `plugin_forms`, `plugin_security`
  - table lookup elided
- `php -l lanes/libsqlite/src/SQLitePlannerSubqueryExpressionIndexCurrentSourceNextPlan.php`
  - no syntax errors
- `php -l lanes/libsqlite/tests/SQLitePlannerSubqueryExpressionIndexCurrentSourceNext123Test.php`
  - no syntax errors
- `php -l lanes/libsqlite/examples/application-subquery-expression-index-current-source-next123.php`
  - no syntax errors
- `git diff --check -- lanes/libsqlite`
  - clean

Dependency closure: no new support component is needed; this composes existing
native expression-index metadata and bounded subquery key materialization.

Non-overlap: this avoids accepted next115 subquery-covering partial-index
payload behavior, next106 partial-index IN-subquery cursor routing,
expression-index range-cost ranking, expression ORDER BY, JSON table planner,
WAL/VFS/B-tree storage clusters, and encoding GLOB/LIKE clusters. The new
surface is subquery-produced expression keys fenced against the current
expression-index source.
