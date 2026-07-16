# SQLite Collation Index Expression Current/Next 56

## Scope

- Added `SQLiteExpressionIndexCollationCursor` for bounded expression-index cursor stepping where current/next comparisons must honor each indexed expression term's collation, direction, and numeric affinity.
- Covered builtin `BINARY`, `NOCASE`, and `RTRIM` collations plus supplied custom callbacks such as a Application slug collation that treats `_`, `-`, case, and trailing spaces consistently.
- Kept the slice disjoint from accepted Unicode GLOB, expression `ORDER BY`, expression-index range-cost, JSON hidden/visible constraints, VFS writer/lock/sync, WAL savepoint/rollback/checkpoint, and B-tree page/freelist work.

## Focused Evidence

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteCollationIndexExpressionCurrentNext56Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 50 assertions, 0 failures
```

## Application Smoke

`lanes/libsqlite/examples/application-collation-index-expression-current-next56.php` reports copied `wp_options` expression-index scans preserving current/next boundaries under a custom `lower(option_name)` collation, `RTRIM` prefix terms, and numeric length affinity before rowid tie-breaks.

## Dependency Closure

No new support component is needed. The patch reuses existing native PHP scalar/collation semantics and adds a lane-local cursor helper for expression-index current/next behavior.
