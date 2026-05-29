# SQLite planner covering partial range current-source

Status: focused PHP behavior growth for an ordinary covering partial range
planner current-source handoff.

This slice adds `SQLitePlannerCoveringPartialRangeCurrentSourceNextPlan`.
It composes the existing `SQLiteMultiColumnRangePlan` with current/next source
fences so a prepared `wp_options` partial covering index scan can reprepare to
the current schema/stat4 source, keep the range cursor on the partial covering
index, elide table lookup, and materialize current-source covered rows.

WordPress path:
`wordpress-planner-covering-partial-range-current-source.php` models a
copied `wp_options` plugin import where a partial covering index over
`blog_id, autoload, option_name, option_value, rowid` should satisfy
`autoload = 'yes' AND option_name >= 'plugin_'` plus the query range
`option_name < 'plugin_z'` from the current source.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerCoveringPartialRangeCurrentSourcePlanTest.php`
  - `1 test files, 59 assertions, 0 failures`
- `php lanes/libsqlite/examples/wordpress-planner-covering-partial-range-current-source.php --self-test`
  - `wordpress-planner-covering-partial-range-current-source self-test passed`

PASS delta: `+59` focused assertions. `lane-status.json` `phpPass` moves from
`54864` to `54923`. Mapped upstream coverage remains `606 / 1589`; this reuses
already mapped partial-index, covering-index, range-planner, and current-source
planner evidence rather than claiming a fresh manifest-backed row.

Non-overlap: avoids accepted partial expression skip-scan, raw-column partial
covering skip-scan, expression ORDER BY, expression-index range-cost ranking,
STAT4 partial expression covering, parser-level JSON table source/cursor work,
and VFS/WAL/B-tree current-source clusters. The new surface is ordinary
non-skip-scan covering partial range current-source materialization.

Dependency closure: no new support component is needed. The patch reuses
lane-local CREATE INDEX parsing, partial predicate implication, multicolumn
range planning, STAT4 metadata, and bounded row materialization.
