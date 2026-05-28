# SQLite planner STAT4 expression partial current-source next249

Status: focused PHP behavior growth for
`sqlplanner-stat4-expression-partial-current-source-next249`.

This slice adds `SQLitePlannerStat4ExpressionPartialCurrentSourceNext249Plan`,
an additive duplicate peer-count fence for partial expression-index STAT4
plans. After accepted next245 proves sample rowid anchors against the current
source, next249 also verifies each STAT4 sample's leading `neq` duplicate count
against the current partial rowset for the expression key plus blog id. This
catches stale duplicate histograms for WordPress option-name peers even when the
sample rowids still exist and anchor correctly.

WordPress path:
`wordpress-sqlplanner-stat4-expression-partial-current-source-next249.php`
models copied `wp_options` plugin rows where `lower(option_name)` has three
case-variant `plugin_forms` peers under a partial expression index. Reusing a
prepared plan with stale peer counts would mis-cost the partial scan; the new
fence records current peer counts and rejects stale duplicate counts before
reuse.

Verification:

- `php -l lanes/libsqlite/src/SQLitePlannerStat4ExpressionPartialCurrentSourceNext249Plan.php`
- `php -l lanes/libsqlite/examples/wordpress-sqlplanner-stat4-expression-partial-current-source-next249.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext249Test.php`
- `php lanes/libsqlite/examples/wordpress-sqlplanner-stat4-expression-partial-current-source-next249.php --self-test`
- `git diff --check -- lanes/libsqlite`

PASS delta: `+67` focused PASS lines from the new next249 test file.
`lane-status.json` `phpPass` moves from `131296` to `131363`. Mapped upstream
coverage remains `663 / 1589`; this reuses already mapped STAT4 expression
partial planner inventory without claiming a fresh upstream runner row.

Non-overlap: avoids accepted next245 sample-rowid anchor validation, next244
LIMIT/OFFSET window validation, next243 sample-tape validation, next240 partial
predicate implication, expression `ORDER BY`, range-cost ranking, JSON, WAL,
VFS, B-tree, trigger, UTF, and suite-runner clusters. The new behavior is only
current-source STAT4 duplicate peer-count validation for partial expression
indexes.

Dependency closure: no new support component is needed. The slice reuses the
existing native PHP planner source arrays, STAT4 sample metadata, partial
predicate row filtering, and focused TestRunner harness.
