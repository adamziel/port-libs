# rowvalue-update-delete-returning-current-source-next142

Adds native PHP support for row-value `IS DISTINCT FROM` and
`IS NOT DISTINCT FROM` inside `SQLiteUpdateDeleteReturningSql`.

The focused slice covers UPDATE/DELETE `WHERE` selection and RETURNING
projection over copied `wp_options` rows:

- null-safe row-value drift detection;
- aligned `NULL` pairs for `IS NOT DISTINCT FROM`;
- one-sided `NULL` differences for `IS DISTINCT FROM`;
- integer/real numeric equality;
- text-vs-numeric storage-class distinction;
- scalar `AND` composition and malformed arity/missing-column guards.

Focused evidence:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningDistinctCurrentSourceNext142Test.php
```

Result:

```text
1 test files, 53 assertions, 0 failures
53 PASS lines
```

Application smoke:

```sh
php lanes/libsqlite/examples/application-rowvalue-distinct-returning-current-source-next142.php --self-test
```

Result:

```text
application-rowvalue-distinct-returning-current-source-next142 self-test passed
```

Dashboard delta: update `phpPass` by the verified focused PASS-line delta
`+53`, from `62524` to `62577`. Mapped upstream coverage is unchanged; this is
additional current-source PHP behavior over already mapped row-value and
UPDATE/DELETE RETURNING surfaces.

Non-overlap: avoids accepted SELECT null-distinct current-source coverage,
row-value `IS` / `IS NOT` UPDATE/DELETE coverage, row-value conflict/savepoint
RETURNING slices, UPSERT RETURNING slices, trigger/FK RETURNING behavior, and
storage/VFS/WAL/B-tree/JSON/planner clusters. The new behavior is specifically
row-value `IS DISTINCT FROM` / `IS NOT DISTINCT FROM` in the bounded
UPDATE/DELETE RETURNING executor.

Dependency closure: no new support component is needed. The slice reuses the
lane-local UPDATE/DELETE RETURNING executor, row-value parser, projection
callbacks, and Application row-array smoke path.
