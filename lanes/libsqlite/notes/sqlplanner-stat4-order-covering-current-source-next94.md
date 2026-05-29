# sqlplanner-stat4-order-covering-current-source-next94

## Scope

Adds a focused current-source planner wrapper for STAT4 covering indexes that
also satisfy `ORDER BY`. The behavior is deliberately distinct from accepted
skip-scan ORDER planning, STAT4 partial-covering planning, expression range
costing, and SQL expression `ORDER BY`: this slice tracks stale prepared-source
invalidation and confirms the selected current plan elides both table rowid
lookups and temp B-tree sorting for a WordPress-style `wp_options` plugin scan.

## Evidence

- Focused test: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteStat4OrderCoveringCurrentSourceNext94Test.php`
- Example smoke: `php lanes/libsqlite/examples/wordpress-planner-stat4-order-covering-current-source-next94.php --self-test`
- Lint: `php -l lanes/libsqlite/src/SQLiteStat4OrderCoveringCurrentSourceNextPlan.php`, `php -l lanes/libsqlite/tests/SQLiteStat4OrderCoveringCurrentSourceNext94Test.php`, `php -l lanes/libsqlite/examples/wordpress-planner-stat4-order-covering-current-source-next94.php`
- Diff check: `git diff --check -- lanes/libsqlite`

## Dependency Closure

No new support component is needed. The slice reuses the existing native PHP
planner helpers `SQLitePartialIndexOrderCurrentSourcePlan`,
`SQLiteMultiColumnRangePlan`, and `SQLiteIndexPredicate`.

## Non-Overlap

Avoided accepted STAT4 skip-scan ORDER current-source next87, STAT4 partial
covering current-source next90, expression-index range-cost planning, SQL
expression `ORDER BY`, and JSON/BTREE/WAL/VFS current-source surfaces.
