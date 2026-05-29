# compound-select-affinity-recursive-order-current-source-next140

## Behavior

Implemented SQLite-style storage-class ordering for recursive CTE queue `ORDER BY`
evaluation. Recursive queue ordering now ranks `NULL < numeric < text < blob`
instead of comparing mixed numeric/text values as strings, which fixes compound
SELECT results that read from a recursive CTE before applying the final compound
`ORDER BY`/`LIMIT`.

The focused WordPress smoke models copied `wp_options` hierarchy rows where
current and next source snapshots contain mixed numeric and text sort keys. The
new `plugin_beta` branch changes the recursive queue boundary and final
compound rowset without requiring ext/sqlite.

## Verification

Focused command:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteCompoundSelectAffinityRecursiveOrderCurrentSourceNext140Test.php
Focused test run: 1 selected test files (root lock skipped)
44 PASS lines
1 test files, 170 assertions, 0 failures
```

Example smoke:

```text
php lanes/libsqlite/examples/wordpress-compound-recursive-affinity-order-current-source-next140.php
```

PHP lint:

```text
php -l lanes/libsqlite/src/SQLiteSelectSql.php
php -l lanes/libsqlite/src/SQLiteCompoundSelectAffinityRecursiveOrderCurrentSourceNextPlan.php
php -l lanes/libsqlite/tests/SQLiteCompoundSelectAffinityRecursiveOrderCurrentSourceNext140Test.php
php -l lanes/libsqlite/examples/wordpress-compound-recursive-affinity-order-current-source-next140.php
```

Diff hygiene:

```text
git diff --check -- lanes/libsqlite
```

## Non-overlap

Avoids accepted compound row composition, compound LIMIT/window affinity,
compound CTE/window ordering, expression `ORDER BY`, grouped SELECT SQL text,
JSON table sources/constraints, WAL/VFS/pager, B-tree, UTF-16 malformed guard,
and Unicode GLOB clusters. This slice is narrower: recursive CTE queue
`ORDER BY` mixed storage-class affinity, observed through a compound SELECT
over current/next source snapshots.

## Dependency Closure

No new support component is needed. The patch reuses the existing native PHP
`SQLiteSelectSql`, recursive CTE trace, compound SELECT, and WordPress row-array
execution components.
