# Compound SELECT Window Recursive LIMIT Current Source CurrentPageHandoff

## Behavior

Adds `SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan`, a
bounded current-source handoff fence for compound SELECT text that combines:

- `WITH RECURSIVE` queue `LIMIT/OFFSET`;
- `dense_rank()` window output over recursive and copied `wp_options` rows;
- `UNION` distinct plus `EXCEPT`;
- final `ORDER BY` and `LIMIT/OFFSET`.

The new current-page-handoff layer requires exact acknowledgement tokens for every row in
the current limited page before exposing the next-source cursor. That catches a
WordPress import preview edge where a newly staged autoloaded option changes the
next-source dense-rank page while the current page is still being drained.

## Evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitCurrentPageHandoffTest.php`
  - `1 test files, 412 assertions, 0 failures`
  - `74` PASS lines
- `php lanes/libsqlite/examples/wordpress-compound-select-window-recursive-limit-current-page-handoff.php`
  - JSON self-test payload emitted with a 3-row acknowledgement fence

## Non-Overlap

This avoids accepted union-except-dense-rank-limit dense-rank UNION/EXCEPT row composition by adding
the next-source handoff cursor after current-page acknowledgements. It also
avoids current-page-drain current-page drain over next224 rank/window behavior, next226
sum/count EXCEPT+INTERSECT, JSON table, WAL/VFS, B-tree, encoding, planner
range-cost, trigger, and status-only surfaces.

## Dependency Closure

No new support component is needed. This reuses native PHP `SQLiteSelectSql`
compound execution, recursive LIMIT/OFFSET tracing, dense-rank window output,
UNION/EXCEPT membership, current-source tokens, and final LIMIT helpers.
