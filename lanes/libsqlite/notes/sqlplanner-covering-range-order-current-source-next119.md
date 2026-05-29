# SQL planner covering range order current-source next119

Implemented `SQLitePlannerCoveringRangeOrderCurrentSourceNextPlan` for
ordinary multicolumn covering range scans that must be replanned against the
current schema/STAT4 source before streaming `ORDER BY` from the index cursor.

Behavior covered:

- stale prepared statements select the current source when schema cookies,
  STAT4 generation, root pages, or index signatures change;
- equality-prefix plus range-column planning for
  `(blog_id, autoload, option_name, option_value, option_id)`;
- partial-index implication from `autoload = 'yes'`;
- covering output columns elide table rowid lookups;
- index order elides temp sorting when `ORDER BY option_name, option_value`
  matches the post-equality index suffix;
- STAT4 current/next samples expose matched range keys and cursor advance
  boundaries;
- fallback paths record deferred table lookup, block sorting, partial-index
  rejection, inclusive `BETWEEN`, open lower-bound seeks, and validation
  failures.

Verification:

- `php -l lanes/libsqlite/src/SQLitePlannerCoveringRangeOrderCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLitePlannerCoveringRangeOrderCurrentSourceNext119Test.php`
- `php -l lanes/libsqlite/examples/wordpress-planner-covering-range-order-current-source-next119.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerCoveringRangeOrderCurrentSourceNext119Test.php`
  - `1 test files, 77 assertions, 0 failures`
- `php lanes/libsqlite/examples/wordpress-planner-covering-range-order-current-source-next119.php --self-test`
  - `wordpress-planner-covering-range-order-current-source-next119 self-test passed`

Expected dashboard movement: `phpPass +77` after clean integration, no mapped
upstream denominator movement claimed.

Dependency closure: no new support component is needed; this composes existing
native PHP `CREATE INDEX` parsing, partial-index predicate implication, STAT4
sample diagnostics, and cursor-plan materialization.

Non-overlap: avoids accepted expression `ORDER BY`, expression-index range
cost, STAT4 expression covering, subquery covering partial indexes, parser-level
SELECT text, grouped SELECT, JSON table, WAL/VFS, and B-tree accepted clusters.
The new surface is ordinary multicolumn covering range `ORDER BY` current-source
cursor materialization for WordPress `wp_options` plugin-option scans.
