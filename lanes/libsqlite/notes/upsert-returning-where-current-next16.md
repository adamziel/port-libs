# UPSERT RETURNING WHERE Current Row, Next16

## Behavior

Adds bounded parser-level execution for:

```sql
INSERT INTO wp_options(...)
VALUES (...)
ON CONFLICT(option_name) DO UPDATE SET ...
WHERE ...
RETURNING ...
```

The slice covers conflict updates gated by current target-row predicates,
`excluded` predicates, skipped conflicts, inserted rows, repeated incoming rows
that see earlier statement changes, and RETURNING projection/alias/wildcard
rows. This is deliberately separate from the accepted callable
`SQLiteUpsertDoUpdateWherePlan` corpus and from accepted UPDATE/DELETE
RETURNING, SELECT text, GROUP BY, subquery, VFS, WAL, JSON table, and B-tree
clusters.

## Verification

Focused command:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteUpsertReturningSqlTest.php
```

Result:

```text
Focused test run: 1 selected test files (root lock skipped)
60 PASS lines
1 test files, 60 assertions, 0 failures
```

Expected dashboard movement: `phpPass +60` from `5433` to `5493`. Mapped
upstream denominator is unchanged because this is a focused behavior executor
slice, not a newly mapped upstream inventory unit.

## Application Smoke

`lanes/libsqlite/examples/application-upsert-returning-where-current.php` previews
copied `wp_options` import rows where a conflict updates only when the current
row satisfies the `WHERE` clause, while skipped conflicts produce no RETURNING
row.

## Dependency Closure

No new support component is needed. The slice reuses existing native PHP row
array execution, SQLite-style LIKE/GLOB helpers, and
`SQLiteUpsertDoUpdateWherePlan` mutation semantics.
