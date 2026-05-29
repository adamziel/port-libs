# rowvalue-returning-savepoint-distinct-current-source-next149

Adds focused current-source coverage for row-value `IS DISTINCT FROM` and
`IS NOT DISTINCT FROM` in UPDATE/DELETE RETURNING statements when the row-value
literal side uses SQLite real numeric literals.

The behavior change extends `SQLiteUpdateDeleteReturningSql` numeric literal
parsing from integers only to SQLite-style real literals, including decimal and
exponent forms. That lets savepoint-wrapped UPDATE/DELETE RETURNING execution
preserve upstream parity for numeric integer/real equality while still treating
text numeric storage as distinct.

Focused evidence:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueReturningSavepointDistinctCurrentSourceNextTest.php
```

Result:

```text
1 test files, 50 assertions, 0 failures
50 PASS lines
```

WordPress smoke:

```sh
php lanes/libsqlite/examples/wordpress-rowvalue-returning-savepoint-distinct-current-source.php --self-test
```

Result:

```text
wordpress-rowvalue-returning-savepoint-distinct-current-source self-test passed
```

Expected dashboard delta: `phpPass` moves from `66428` to `66478` from 50
newly passing focused PASS lines. Mapped upstream coverage remains `606 / 1589`;
this is additional focused PHP behavior over already mapped row-value,
UPDATE/DELETE RETURNING, and savepoint current-source inventory.

Non-overlap: this avoids accepted row-value RETURNING DISTINCT savepoint
next145, row-value UPDATE/DELETE DISTINCT next142, row-value savepoint `IS` /
`IS NOT` next133, row-value DELETE/UPDATE savepoint next135, trigger/UPSERT
RETURNING savepoint clusters, and storage/VFS/WAL/B-tree/JSON/planner surfaces.
The new surface is specifically real numeric literal parsing inside row-value
distinct predicates and RETURNING expressions under the savepoint current-source
executor.

Dependency closure: no new support component is needed. The slice reuses the
lane-local UPDATE/DELETE RETURNING executor, row-value predicate parser,
savepoint current-source wrapper, and WordPress row-array smoke path.
