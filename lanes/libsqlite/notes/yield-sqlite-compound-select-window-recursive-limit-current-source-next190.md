# yield-sqlite-compound-select-window-recursive-limit-current-source-next190

Slice: `compound-select-window-recursive-limit-current-source-next190`.

Adds `SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan`, a
bounded current-source comparison for compound SELECTs that combine:

- a recursive CTE whose queue LIMIT/OFFSET are SQL expressions;
- window-function arms evaluated before compound UNION/UNION ALL composition;
- final compound ORDER BY with expression-valued LIMIT/OFFSET;
- current versus next copied `wp_options` source boundaries.

Focused verification:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext190Test.php
# Focused test run: 1 selected test files (root lock skipped)
# 1 test files, 340 assertions, 0 failures
```

Application smoke:

```sh
php lanes/libsqlite/examples/application-compound-select-window-recursive-limit-current-source-next190.php --self-test
# application-compound-select-window-recursive-limit-current-source-next190 self-test passed
```

Dependency closure: no new support component needed. The slice reuses native
`SQLiteSelectSql` expression-valued LIMIT/OFFSET evaluation, recursive CTE
tracing, compound SELECT execution, and window result dispatch.

Non-overlap: avoids accepted next187 negative recursive LIMIT/OFFSET,
next185 single-row recursive LIMIT/OFFSET, next186 comma-form compound LIMIT,
accepted grouped SELECT text, expression ORDER BY, JSON table, WAL, VFS,
B-tree, PRAGMA, trigger, row-value, and encoding clusters. This patch is
limited to expression-valued recursive and final compound LIMIT/OFFSET
boundaries with window arms.
