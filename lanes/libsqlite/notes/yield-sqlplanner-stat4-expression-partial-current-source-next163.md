# sqlplanner-stat4-expression-partial-current-source-next163

## Behavior

Adds `SQLiteStat4ExpressionPartialCurrentSourceNextPlan`, a bounded planner
diagnostic for stale prepared statements that must re-read the current source
before choosing a partial expression index from STAT4 equality+range samples.

The slice models a copied `wp_options` lookup on `lower(option_name)` with
partial predicates (`autoload = 'yes'`, `option_name IS NOT NULL`) and an
`updated_at` range. It verifies current-source reprepare fences, matched STAT4
sample rowids/keys, inclusive and open range cursor opcodes, sparse competing
partial-index selection, and malformed source/sample validation.

## Verification

Focused command:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteStat4ExpressionPartialCurrentSourceNext163Test.php
```

Result:

```text
1 test files, 66 assertions, 0 failures
```

Example smoke:

```sh
php lanes/libsqlite/examples/application-stat4-expression-partial-current-source-next163.php
```

Result: emits JSON with `status` =
`stat4-expression-partial-current-source-next163-ready`, `selectedSource` =
`current`, `selectedIndex` =
`idx_wp_options_lower_autoload_updated_next163`, and matched rowids `[22,23]`.

## Non-Overlap

This avoids accepted expression-index range-cost ranking, expression `ORDER BY`,
expression partial covering/current-source rows, STAT4 collation boundary
diagnostics, JSON table, VFS/WAL, and B-tree clusters. It focuses only on
partial expression-index choice from current-source STAT4 equality+range samples
after stale prepared-source detection.

## Dependency Closure

No new support component is needed. The slice reuses lane-local expression
metadata conventions, partial predicate implication, and native PHP STAT4
sample selectivity diagnostics.
