# Yield SQLite Planner Expression Covering STAT4 Current Next37

This slice adds bounded `sqlite_stat4`-style sample estimates to
`SQLiteSelectExpressionIndexPlan` for expression-covering indexes. The planner
now accepts first-key `stat4Samples`, uses `neq` for equality and `IN`,
uses `nlt`/`neq` boundaries for range and `BETWEEN`, preserves current/next
sample evidence, and keeps the existing covering tail and order compatibility
costing.

Focused behavior:

- lower/upper/cast expression-index constraints can use STAT4 samples.
- exact equality, unknown equality, deduplicated `IN`, range, reversed range,
  and `BETWEEN` estimates are bounded by the base estimate.
- current/next sample pairs are emitted for planner evidence.
- malformed STAT4 sample lists, rows, vectors, and count tokens are rejected.
- copied `wp_options` smoke shows a skewed `lower(option_name)` covering index
  winning for Application option-name lookups without `ext/sqlite`.

Verification:

```bash
php -l lanes/libsqlite/src/SQLiteSelectExpressionIndexPlan.php
php -l lanes/libsqlite/tests/SQLitePlannerExpressionCoveringStat4CurrentNext37Test.php
php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerExpressionCoveringStat4CurrentNext37Test.php
```

Result:

```text
1 test files, 91 assertions, 0 failures
```

New focused PASS-line delta: 66.

Non-overlap: this does not repeat accepted expression-index range-cost
ranking, expression `ORDER BY`, partial-index proof, partial-WHERE covering
proof, skip-scan partial indexes, SELECT SQL text/subqueries/grouping, JSON
table source/cursor/constraint work, VFS/WAL transaction application, B-tree
page move/root-collapse/overflow release, Unicode GLOB, or batch23 surfaces.
It is limited to STAT4 current/next sample estimates for expression-covering
planner choices.

Dependency closure: no new support component is needed. The implementation
reuses existing lane-local expression-index parsing, covering-tail inference,
literal validation, and planner ranking.
