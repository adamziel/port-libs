# UPDATE FROM current next25

## Behavior

This slice extends the bounded `SQLiteUpdateFromSql` executor for WordPress
current-row imports:

- preserves a leading `WITH` / CTE clause when generating the internal SELECT
  used to identify target rows and assignment values;
- supports `UPDATE wp_options AS current ...` and `UPDATE wp_options current ...`
  target aliases, including alias-qualified predicates and assignment
  expressions;
- preserves top-level `ORDER BY`, `LIMIT`, comma-form `LIMIT offset,count`, and
  `OFFSET` clauses when the bounded executor rewrites `UPDATE ... FROM` into
  the internal row-selection SELECT;
- keeps existing current-row behavior for duplicate source rows and `OR REPLACE`
  unique `option_name` conflicts.

The WordPress path is copied `wp_options` import staging where current rows are
updated from a CTE-backed staging set without requiring ext/sqlite.

## Evidence

Focused command:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteUpdateFromCurrentNext25Test.php
```

Result:

```text
Focused test run: 1 selected test files (root lock skipped)
1 test files, 82 assertions, 0 failures
```

Example smoke:

```sh
php lanes/libsqlite/examples/wordpress-update-from-current-next25.php
```

The smoke now includes an ordered/limited staged `wp_options` import and emits
the preserved `ORDER BY ... LIMIT` tail.

## Non-overlap

Avoids accepted batch23/current surfaces: duplicate-source current behavior,
`UPDATE OR REPLACE` current unique-conflict deletion, derived-table materialized
SELECT staging, grouped SELECT SQL text, SELECT subquery execution, JSON table
SELECT sources/hidden/visible constraints, and VFS/WAL/B-tree accepted clusters.
This patch is specifically parser preservation for leading CTEs, target
aliases, and top-level ordered/limited row selection inside the existing
bounded `UPDATE FROM` executor.

## Dependency closure

No new support component is needed. The slice reuses existing bounded native PHP
SELECT SQL, CTE, predicate, scalar expression, and current row-array update
components.
