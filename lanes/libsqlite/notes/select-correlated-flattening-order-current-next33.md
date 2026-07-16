# SELECT Correlated Flattening Order Current/Next33

Slice: `yield-sqlite-select-correlated-flattening-order-current-next33`.

Status delta:

- Added parser-level current-row propagation for compound SELECT arms planned
  inside correlated derived tables.
- Correlated derived bodies with `UNION`, `UNION ALL`, `INTERSECT`, and
  `EXCEPT` can now read the outer row before derived-table `ORDER BY`,
  `LIMIT`, and `OFFSET` decide the current/next row yielded to the parent
  subquery.
- Added a Application import-staging smoke where copied `wp_options` rows choose
  the first metadata/stage key through a correlated compound derived table.

Focused evidence:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteSelectCorrelatedFlatteningOrderCurrentNext33Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 51 assertions, 0 failures
```

Expected dashboard movement:

- `phpPass`: `11206 -> 11257` (+51 focused PASS lines from the new lane test).
- `benchmarkDenominator.mapped`: unchanged; no new upstream inventory unit was
  mapped in this slice.

Dependency closure:

No new support component is needed. The slice reuses the existing bounded
native PHP `SQLiteSelectSql`, `SQLiteSelectQuery`, `SQLiteSelectCompound`,
projection, predicate, and result-ordering machinery.

Non-overlap:

This avoids accepted SELECT SQL text/JOIN/GROUP/subquery/comma-LIMIT/
expression-ORDER clusters, JSON table source/cursor/hidden/visible constraint
work, VFS writer/sync/lock/rollback clusters, WAL checkpoint/savepoint byte
truncation, B-tree page move/root-collapse/overflow freelist release,
Unicode GLOB, and batch23/27 metadata/planner/VDBE work. The new behavior is
specifically compound derived-table arm planning in correlated subqueries so
each outer row gets its own ordered current/next inner rowset.
