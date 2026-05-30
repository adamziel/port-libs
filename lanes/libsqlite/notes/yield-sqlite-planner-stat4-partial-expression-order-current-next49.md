# SQLite planner STAT4 partial expression order current/next49

This slice extends `SQLiteSelectExpressionIndexPlan` so partial expression
indexes can satisfy `ORDER BY` terms that name the indexed expression followed
by ordinary trailing index columns. The planner now recognizes expression order
operands such as `lower(option_name)` and `json_extract(option_value, '$.kind')`
before checking trailing-column order, while preserving accepted STAT4
current/next selectivity, partial predicate proof, and covering-tail metadata.

Focused behavior:

- `ORDER BY lower(option_name), autoload, option_id DESC` is satisfied by a
  partial expression index on `(lower(option_name), autoload, option_id DESC)`.
- STAT4 estimates and current/next sample evidence are retained for equality,
  range, `BETWEEN`, and `IN` constraints on the expression key.
- Tail order direction, skipped tail columns, mismatched expression functions,
  JSON path mismatches, and malformed ORDER BY terms are rejected.
- Application smoke:
  `examples/application-planner-stat4-partial-expression-order-current-next49.php`
  previews copied `wp_options` plugin-option scans that avoid a separate sort.

Verification:

```text
php -l lanes/libsqlite/src/SQLiteSelectExpressionIndexPlan.php
No syntax errors detected in lanes/libsqlite/src/SQLiteSelectExpressionIndexPlan.php

php -l lanes/libsqlite/tests/SQLitePlannerStat4PartialExpressionOrderCurrentNext49Test.php
No syntax errors detected in lanes/libsqlite/tests/SQLitePlannerStat4PartialExpressionOrderCurrentNext49Test.php

php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4PartialExpressionOrderCurrentNext49Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 77 assertions, 0 failures
```

Non-overlap: this avoids accepted STAT4 partial ordinary covering ORDER
planning, accepted expression-covering STAT4 estimates, expression `ORDER BY`
SQL text execution, partial-index implication proof, JSON hidden/visible table
constraints, VFS/WAL/B-tree apply clusters, and batch38 STAT4 partial covering
ORDER behavior. The new surface is ORDER compatibility when the ORDER BY term
itself is the partial expression-index key and the scan continues through
ordinary trailing index columns.

Dependency closure: no new support component is needed. This reuses the
existing CREATE INDEX expression parser, partial predicate proof, STAT4 sample
normalization, and expression-index covering planner.
