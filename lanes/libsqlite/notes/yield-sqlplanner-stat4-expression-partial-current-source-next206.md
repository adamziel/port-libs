# sqlplanner-stat4-expression-partial-current-source-next206

## Behavior

Adds a current-source STAT4 expression partial-index planner fence for partial
indexes whose changed WHERE predicate includes OR arms. The plan reuses the
accepted next195 conjunctive predicate, payload-expression, peer-rowid, LIMIT,
and STAT4 fences, then admits the current index only when one current partial
OR arm is implied by the query and every selected row satisfies at least one
OR arm.

The WordPress smoke models a copied `wp_options` expression index on
`lower(option_name)` with a partial predicate like:

`autoload = 'yes' AND option_name IS NOT NULL AND (blog_id = 1 OR autoload = 'critical')`

This closes the planner gap where a prepared statement could otherwise reuse
current STAT4 samples from a changed partial index without proving the OR
portion of the current partial predicate.

## Evidence

Focused commands run in this worktree:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext206Test.php`
- `php lanes/libsqlite/examples/wordpress-stat4-expression-partial-or-current-source-next206.php`
- `php -l lanes/libsqlite/src/SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext206Test.php`
- `php -l lanes/libsqlite/examples/wordpress-stat4-expression-partial-or-current-source-next206.php`
- `git diff --check -- lanes/libsqlite`

## Non-Overlap

Avoids accepted next195 conjunctive partial predicate fencing, expression
ORDER BY, range-cost ranking, JSON, WAL, VFS, B-tree, trigger, and UTF
clusters. This slice only handles OR-arm proof for changed current-source
partial-index predicates.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PHP
STAT4 expression partial-index planner data model and adds only lane-local
proof/fence logic.
