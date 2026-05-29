# Compound SELECT Window Recursive LIMIT Current Source Next166

Status: focused current-source behavior growth for parser-level compound SELECT
execution where a `WITH RECURSIVE` queue is exhausted by `LIMIT 0` before rows
reach the compound arm. Window rows from the sibling `wp_options` arm still rank
normally, and the final compound `ORDER BY ... LIMIT ... OFFSET` decides the
current/next boundary after the empty recursive arm is combined.

Behavior covered:

- recursive CTE queue `LIMIT 0` traces the anchor as not emitted;
- both current and next sources expose an empty recursive arm before window
  evaluation;
- sibling `wp_options` window arm ranks visible autoload rows without leaked
  recursive seed rows;
- final compound LIMIT/OFFSET moves the WordPress current/next boundary when a
  new `rewrite_rules` row enters ahead of the older rows.

Focused verification:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteCompoundExhaustedRecursiveWindowLimitCurrentSourceNext166Test.php
php lanes/libsqlite/examples/wordpress-compound-exhausted-recursive-window-limit-current-source-next166.php --self-test
php -l lanes/libsqlite/src/SQLiteCompoundExhaustedRecursiveWindowLimitCurrentSourceNextPlan.php
php -l lanes/libsqlite/tests/SQLiteCompoundExhaustedRecursiveWindowLimitCurrentSourceNext166Test.php
php -l lanes/libsqlite/examples/wordpress-compound-exhausted-recursive-window-limit-current-source-next166.php
git diff --check -- lanes/libsqlite
```

Expected dashboard movement: `phpPass +57` from the new focused test file.
Mapped coverage remains `610 / 1589`; this uses already mapped recursive CTE,
compound SELECT, window, and LIMIT inventory rather than a new upstream row.

Non-overlap: avoids accepted next163 multi-anchor recursive/window/final LIMIT
behavior, next158 recursive LIMIT/OFFSET window boundary behavior, next159
comma-form final LIMIT yield behavior, accepted SELECT SQL GROUP/JOIN/subquery/
ORDER/LIMIT clusters, JSON table source/cursor/constraint work, WAL/VFS/B-tree
clusters, and suite evidence handoffs. The new surface is `LIMIT 0` recursive
queue exhaustion before compound/window/final LIMIT execution.

Dependency closure: no new support component is needed; this reuses lane-local
`SQLiteSelectSql` recursive CTE tracing, compound SELECT execution, window
row-array evaluation, and result LIMIT/OFFSET machinery.
