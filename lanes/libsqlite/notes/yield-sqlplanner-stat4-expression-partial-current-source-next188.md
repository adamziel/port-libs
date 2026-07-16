# SQLite planner STAT4 expression partial current-source next188

Status: focused PHP behavior growth for a STAT4 expression partial-index
planner peer fence. The accepted next185 sample-provenance path proves that
current LIMIT-window rowids exist in the current source. This follow-up admits
the current-source plan only when duplicate expression-key peers in that
window use deterministic rowid tiebreak order and every peer is bracketed by
current sqlite_stat4 samples.

Application smoke: `application-sqlplanner-stat4-expression-partial-current-source-next188.php`
models copied `wp_options` plugin screens where duplicate mixed-case
`plugin_forms` option names share the same `lower(option_name)` key. The plan
can reuse the partial expression index only when those peers are ordered by
current rowid and bracketed by current STAT4 samples after ANALYZE/source
changes.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext188Test.php`
- `1 test files, 61 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-sqlplanner-stat4-expression-partial-current-source-next188.php --self-test`
- `application-sqlplanner-stat4-expression-partial-current-source-next188 self-test passed`

Dependency closure: no new support component is needed. The slice reuses the
accepted current-source STAT4 expression partial planner chain and adds a
lane-local peer rowid fence.

Non-overlap: avoids accepted next185 sample provenance, next182 LIMIT windows,
next180 descending scans, expression ORDER BY, range-cost ranking, JSON, WAL,
VFS, B-tree, trigger, and UTF clusters. The new behavior is limited to
duplicate expression-key peer admission for current-source partial expression
STAT4 plans.
