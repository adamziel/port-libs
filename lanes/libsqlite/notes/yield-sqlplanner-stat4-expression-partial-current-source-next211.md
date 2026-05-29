# SQLite planner STAT4 expression partial current-source next211

Status: focused PHP behavior growth for STAT4 expression partial-index plan
reuse where the grouped partial OR predicate is already proven, but the cursor
must also prove that the current STAT4 seek-window probe samples and selected
window rowids still resolve to the current source.

WordPress smoke: `wordpress-sqlplanner-stat4-expression-partial-current-source-next211.php`
models copied `wp_options` plugin admin pagination over
`lower(option_name)` after ANALYZE/source changes. The planner admits reuse only
when the current lower/upper STAT4 samples for the DESC windows still point to
the same current rowids/keys and every selected rowid remains inside a current
seek window.

Verification:

- `php -l lanes/libsqlite/src/SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext211Test.php`
- `php -l lanes/libsqlite/examples/wordpress-sqlplanner-stat4-expression-partial-current-source-next211.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext211Test.php`
- `php lanes/libsqlite/examples/wordpress-sqlplanner-stat4-expression-partial-current-source-next211.php --self-test`
- `git diff --check -- lanes/libsqlite`

Dependency closure: no new support component needed. This composes existing
lane-local STAT4 expression partial current-source planning, grouped partial OR
predicate proof, expression-key materialization, and row payload provenance.

Non-overlap: avoids accepted next209 grouped partial OR-arm admission, next206
single-term partial OR fencing, next202 partial definition fencing, expression
ORDER BY, expression-index range costs, JSON table, WAL, VFS, B-tree, trigger,
and encoding clusters. The new surface is the current-source STAT4 seek-window
rowid/sample provenance fence after the partial predicate is already proved.
