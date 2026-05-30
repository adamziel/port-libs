# Row-Value UPDATE/DELETE RETURNING Current Source Next117

Adds bounded `UPDATE` / `DELETE ... RETURNING` expression projection support
for row-value DML current-source paths.

Behavior:

- `RETURNING` projection terms may use expression aliases with `AS`, including
  `||` concatenation, `+` arithmetic over columns/literals, `IS NULL` /
  `IS NOT NULL`, row-value comparison predicates, and row-value `IN` /
  `NOT IN` predicates.
- `DELETE ... RETURNING` expressions are evaluated against the old row image.
- `UPDATE ... RETURNING` expressions are evaluated against the new row image
  after row-value assignment callbacks have applied.
- Row-value expression results use SQLite-style `1`, `0`, or `NULL` truth
  values and preserve existing arity/identifier validation.
- Bare RETURNING expressions without an alias are rejected so bounded preview
  output keys remain deterministic.

Focused evidence:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteUpdateDeleteRowValueReturningCurrentSourceNext117Test.php lanes/libsqlite/tests/SQLiteUpdateDeleteRowValueCurrentSourceNext110Test.php lanes/libsqlite/tests/SQLiteUpdateDeleteReturningSqlTest.php
```

Result: `3 test files, 135 assertions, 0 failures`.

New focused PASS-line delta: `+30` from
`SQLiteUpdateDeleteRowValueReturningCurrentSourceNext117Test.php`.

Application smoke:

```sh
php lanes/libsqlite/examples/application-row-value-returning-current-source-next117.php --self-test
```

Dependency closure: no new support component is needed. This reuses the native
PHP row-array `SQLiteUpdateDeleteReturningSql`, `SQLiteUpdateDeleteLimitPlan`,
and existing SQLite predicate/expression helpers; no `ext/sqlite`, upstream
binary, VFS, provider credential, or shared dependency row is required.

Non-overlap: avoids accepted next110 row-value predicate/assignment coverage
and accepted batch109-113 row-value UPDATE/DELETE returning surfaces by adding
only RETURNING expression projection over old/new row images. It also avoids
JSON, WAL/VFS, B-tree, trigger/FK, planner, and suite-runner current-source
clusters.
