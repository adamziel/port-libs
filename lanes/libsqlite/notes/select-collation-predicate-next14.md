# SELECT Collation Predicate Next14

## Behavior

- Adds parser-level `COLLATE` predicate semantics for `BINARY`, `NOCASE`, and `RTRIM`.
- Covers equality, inequality, `IS` / `IS NOT`, `IS DISTINCT FROM`, `BETWEEN`, `IN` / `NOT IN`, `HAVING`, `JOIN ON`, row-value predicates, and CTE-fed `IN` subqueries.
- Keeps SQLite text behavior bounded: `NOCASE` is ASCII-only, `RTRIM` ignores trailing ASCII whitespace for text comparison, and numeric/blob storage-class ordering remains unchanged.

## Evidence

Focused command:

```bash
php tools/run-tests.php lanes/libsqlite/tests/SQLiteSelectCollationPredicateNext14Test.php
```

Output:

```text
Focused test run: 1 selected test files (root lock skipped)
55 PASS lines
1 test files, 55 assertions, 0 failures
```

`lane-status.json` `phpPass` is updated by the exact focused PASS-line delta verified in this worktree: `4362 -> 4417`.

## Application Smoke

`lanes/libsqlite/examples/application-select-collation-predicate.php` reports copied `wp_options` lookups where:

- `option_name COLLATE NOCASE = 'SITEURL'` matches both `siteurl` and `SiteURL`.
- `option_name COLLATE RTRIM = 'home'` matches a trailing-space option name.

## Non-overlap

This avoids accepted ORDER BY collation/null placement and Unicode GLOB range behavior. The slice is narrower: predicate comparison semantics inside parser-level SELECT filtering and join predicates.

## Dependency Closure

No new support component is needed. The patch reuses the existing native PHP `SQLiteSelectSql`, `SQLiteSelectPredicate`, and `SQLiteSelectExpression` components.
