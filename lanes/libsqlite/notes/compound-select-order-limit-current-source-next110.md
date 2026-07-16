# Compound SELECT ORDER/LIMIT Current Source Next110

## Behavior

- Adds parser-level compound SELECT tail `ORDER BY` matching for exact projected
  expressions, not just aliases, ordinals, and bare result columns.
- Preserves SQLite's compound tail order: set operation first, then final
  `ORDER BY`, then `LIMIT` / `OFFSET` including comma-form limits.
- Covers arithmetic, concatenation, scalar function, unary, cast, CASE, JSON,
  bitwise, CTE-fed, recursive CTE-fed, `UNION`, `UNION ALL`, `INTERSECT`, and
  `EXCEPT` shapes over copied `wp_options` current/staged rows.

## Evidence

Focused command:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteCompoundSelectOrderLimitCurrentSourceNext110Test.php
```

Result:

```text
1 test files, 46 assertions, 0 failures
```

Smoke command:

```sh
php lanes/libsqlite/examples/application-compound-select-order-limit-current-source-next110.php
```

Result: valid JSON with selected names
`["rewrite_rules","active_plugins","active_plugins","siteurl"]`.

## Non-Overlap

This avoids accepted compound row composition, expression `ORDER BY` for
non-compound SELECT text, comma `LIMIT`, grouped SELECT text, JSON table
source/constraint work, WAL/VFS/B-tree application clusters, and batch106
storage/planner/schema/VDBE surfaces. The slice is specifically compound
SELECT final-tail expression matching and post-sort limit slicing.

## Dependency Closure

No new support component is needed. The patch reuses the existing native PHP
`SQLiteSelectSql`, `SQLiteSelectCompound`, and `SQLiteSelectResult` pipeline.
