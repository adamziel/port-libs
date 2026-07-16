# Recursive DML Current Source Current-Next28

## Scope

This slice adds bounded native PHP support for a single `WITH RECURSIVE` CTE
materialized as the current source for DML statements:

- `INSERT INTO ... SELECT ...` over recursive CTE rows.
- `UPDATE ... FROM cte ...` over recursive CTE rows.
- `UPDATE/DELETE ... WHERE column IN (SELECT column FROM cte) RETURNING ...`.

It avoids accepted recursive SELECT traversal, recursive CTE queue ordering,
SELECT SQL subqueries, UPDATE FROM current-conflict, INSERT SELECT conflict,
rollback/WAL/VFS, and JSON table clusters.

## Evidence

Focused command:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteRecursiveDmlCycleCurrentNext28Test.php
Focused test run: 1 selected test files (root lock skipped)
11 PASS lines, 53 assertions, 0 failures
```

Example smoke:

```text
php lanes/libsqlite/examples/application-recursive-dml-current-source.php
```

Expected `phpPass` movement: `9342 -> 9353` from 11 newly verified focused PASS
lines in this lane-scoped test file.

## Dependency Closure

No new support component is needed. The slice reuses the existing bounded
`SQLiteSelectSql`, `SQLiteInsertSelectSql`, `SQLiteUpdateFromSql`, and
`SQLiteUpdateDeleteReturningSql` components.

## Next

Replay on the newest accepted libsqlite head and continue with non-overlapping
SQL executor/planner or pager/VFS behavior. This slice is intentionally not a
status-only or manifest-only handoff.
