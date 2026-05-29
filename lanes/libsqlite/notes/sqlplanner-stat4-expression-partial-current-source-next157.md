# sqlplanner-stat4-expression-partial-current-source-next157

Status: ready for integration as a focused current-source planner behavior
slice.

Behavior:

- Adds `SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan`, a bounded
  composition over `SQLiteSelectExpressionIndexPlan` for partial expression
  indexes that use STAT4 samples and covering current-source rows.
- Fences prepared/current/next sources by schema cookie, STAT4 generation,
  index signature, row stream signature, and STAT4 signature.
- Materializes current-source rows for a WordPress-shaped
  `lower(option_name)` partial index over copied `wp_options` rows while
  preserving case in the covering payload.
- Rejects a next source when schema/stat4/index/row/sample signatures drift
  before reuse.

Focused evidence:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext157Test.php
```

Result:

```text
Focused test run: 1 selected test files (root lock skipped)
1 test files, 59 assertions, 0 failures
```

WordPress smoke:

```sh
php lanes/libsqlite/examples/wordpress-planner-stat4-expression-partial-current-source-next157.php
```

Expected output:

```text
wordpress planner stat4 expression partial current-source next157: idx_wp_options_lower_name_autoload_stat4_partial_next157 rows=4 rowids=2,7,5,8 names=plugin_beta,PLUGIN_BETA,plugin_stable,plugin_stable
```

Non-overlap:

This avoids accepted STAT4 partial covering ORDER materialization, expression
partial skip-scan, expression-index range-cost ranking, expression ORDER BY,
JSON table source/constraint work, WAL/VFS durability work, and B-tree
freeblock/freelist clusters. The new surface is current-source fencing and
next-source admission for STAT4 expression partial covering row materialization.

Dependency closure:

No new support component is needed. The slice reuses existing native PHP
expression-index parsing, partial predicate proof, STAT4 sample estimates, and
current-source covering row materialization.
