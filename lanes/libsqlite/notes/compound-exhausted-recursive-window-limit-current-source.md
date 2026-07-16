# Compound SELECT Window Recursive LIMIT Current Source

Status: consolidation cleanup for the compound SELECT/window current-source
slice where a `WITH RECURSIVE` queue is exhausted by `LIMIT 0` before rows reach
the compound arm. The production entry point is now the stable `compare()`
method, and the private helper names no longer carry the old worker number.

Behavior covered:

- recursive CTE queue `LIMIT 0` traces the anchor as not emitted;
- both current and next sources expose an empty recursive arm before window
  evaluation;
- sibling `wp_options` window arm ranks visible autoload rows without leaked
  recursive seed rows;
- final compound LIMIT/OFFSET moves the Application current/next boundary when a
  new `rewrite_rules` row enters ahead of the older rows.

Focused verification:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteCompoundExhaustedRecursiveWindowLimitCurrentSourceTest.php
php lanes/libsqlite/examples/application-compound-exhausted-recursive-window-limit-current-source.php --self-test
php -l lanes/libsqlite/src/SQLiteCompoundExhaustedRecursiveWindowLimitCurrentSourceNextPlan.php
php -l lanes/libsqlite/tests/SQLiteCompoundExhaustedRecursiveWindowLimitCurrentSourceTest.php
php -l lanes/libsqlite/examples/application-compound-exhausted-recursive-window-limit-current-source.php
git diff --check -- lanes/libsqlite
```

Expected dashboard movement: no `phpPass` or mapped-coverage change; this is a
numbered-method consolidation that preserves the existing focused assertions.

Non-overlap: avoids accepted next163 multi-anchor recursive/window/final LIMIT
behavior, next158 recursive LIMIT/OFFSET window boundary behavior, next159
comma-form final LIMIT yield behavior, accepted SELECT SQL GROUP/JOIN/subquery/
ORDER/LIMIT clusters, JSON table source/cursor/constraint work, WAL/VFS/B-tree
clusters, and suite evidence handoffs. The new surface is `LIMIT 0` recursive
queue exhaustion before compound/window/final LIMIT execution.

Dependency closure: no new support component is needed; this reuses lane-local
`SQLiteSelectSql` recursive CTE tracing, compound SELECT execution, window
row-array evaluation, and result LIMIT/OFFSET machinery.
