# Row-Value UPDATE/DELETE RETURNING Current Source Next125

This slice extends the bounded native PHP `UPDATE` / `DELETE ... RETURNING`
executor with row-value `BETWEEN` and `NOT BETWEEN` predicates in `WHERE` and
aliased `RETURNING` expressions.

Behavior covered:

- row-value `BETWEEN` lower and upper inclusive range checks;
- `NOT BETWEEN` nullable negation semantics;
- `AND` splitting that preserves the range delimiter inside `BETWEEN`;
- current-source row-value assignment for `UPDATE SET (a, b) = (...)`;
- `RETURNING` row-value predicates over old DELETE rows and new UPDATE rows;
- SQLite-style row-value NULL comparison behavior where a NULL before the
  decisive comparison position makes the result unknown.

Focused evidence:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteUpdateDeleteReturningRowValueCurrentSourceNext125Test.php
1 test files, 52 assertions, 0 failures
```

Application smoke:

```text
php lanes/libsqlite/examples/application-update-delete-returning-rowvalue-current-source-next125.php
```

Non-overlap: this avoids accepted trigger/FK RETURNING savepoint work,
accepted UPDATE/DELETE ORDER BY/LIMIT RETURNING, UPSERT RETURNING, JSON table,
WAL/VFS, B-tree, UTF/collation, and planner clusters. The added behavior is
limited to row-value range predicates and current-source row-value assignment
inside the existing UPDATE/DELETE RETURNING SQL-text executor.

Dependency closure: no new support component is needed. The slice reuses the
existing native PHP row-array DML executor and SQLite comparison helpers.
