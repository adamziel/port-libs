# sqlplanner-stat4-expression-covering-range-current-source

Implemented `SQLitePlannerStat4ExpressionCoveringRangeCurrentSourceNextPlan`
for a current-source STAT4 expression covering range edge. A prepared
`lower(option_name)` covering expression-index cursor may be stale after
schema-cookie, STAT4 generation, root-page, and range-bound changes; this slice
rechecks the current range fence, rejects rowids admitted only by the stale
prepared range, keeps current matched rowids in covering-index order, and
records a VDBE-style cursor tape with no deferred table lookup.

Application path:
`application-stat4-expression-covering-range-current-source.php` models a
copied `wp_options` plugin-option scan where a current source narrows the
prepared plugin option-name range and must avoid returning a stale
`plugin_alpha` row while still reading payload columns from the covering index.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionCoveringRangeCurrentSourceTest.php`
  - `1 test files, 61 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-stat4-expression-covering-range-current-source.php --self-test`
  - `application-stat4-expression-covering-range-current-source self-test passed`

Dashboard delta:

- `phpPass`: `52453 -> 52514` (`+61` focused PASS lines)
- mapped upstream coverage unchanged at `606 / 1589`; this reuses already
  mapped STAT4/expression-index/range-planner inventory without claiming a new
  manifest row.

Non-overlap:

This avoids accepted next122 static bounded range covering STAT4, next126
multi-seek `IN` expression probes, next124 partial-index range predicate
changes, expression `ORDER BY`, expression-index range-cost ranking, JSON
table, VFS/WAL, B-tree, and encoding clusters. The new behavior is stale
prepared range-row rejection plus current-source covering range cursor evidence
after source and range-bound changes.

Dependency closure:

No new support component is needed. The slice reuses native PHP
expression-index parsing, STAT4 samples, bounded range planning, and covering
cursor diagnostics.
