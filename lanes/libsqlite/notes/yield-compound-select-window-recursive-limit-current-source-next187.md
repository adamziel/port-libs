# Compound SELECT Window Recursive LIMIT Current Source Next187

## Slice

Adds focused current-source coverage for recursive CTE `LIMIT -1 OFFSET n`
inside a windowed compound SELECT. This is distinct from accepted exhausted
recursive LIMIT queues and LIMIT 0 slices: the recursive queue remains
unbounded, OFFSET skips the anchor rows, window terms run before compound
UNION/UNION ALL composition, and the final compound LIMIT/OFFSET decides which
current/next Application option rows are visible.

## Evidence

- Focused test: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext187Test.php`
- Application smoke: `php lanes/libsqlite/examples/application-compound-select-window-recursive-limit-current-source-next187.php --self-test`
- PHP lint: `php -l lanes/libsqlite/src/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan.php`, `php -l lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext187Test.php`, `php -l lanes/libsqlite/examples/application-compound-select-window-recursive-limit-current-source-next187.php`
- Diff check: `git diff --check -- lanes/libsqlite`

## Non-overlap

Avoids accepted next181/next182/next184 compound recursive LIMIT work by
covering negative recursive LIMIT semantics, not UNION yield tape alone,
recursive LIMIT 0 suppression, or exhausted positive recursive LIMIT queues.
It also avoids accepted JSON table, WAL, pager, B-tree, PRAGMA, trigger, UTF-16,
and planner surfaces.

## Dependency Closure

No new support component needed. The slice reuses native PHP SELECT SQL
recursive CTE tracing, lag/lead window evaluation, UNION distinct handling,
and final compound LIMIT/OFFSET execution.
