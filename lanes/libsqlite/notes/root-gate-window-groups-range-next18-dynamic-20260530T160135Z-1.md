# Window GROUPS/RANGE Next18 Root Gate Follow-Up

Assigned base: `8bf0d9f81b29a5601901bb34dfd730670ed39bbc`.

Focused reproduction before edit:

```bash
php tools/run-tests.php lanes/libsqlite/tests/SQLiteWindowGroupsRangeCurrentNext18Test.php
# 1 test files, 87 assertions, 0 failures
```

The originally assigned root-gate failure
`upstream corpus window groups range current next18 rejects frame without order`
was already fixed on this accepted base. This follow-up keeps ownership in the
same current-base root-gate family and adds four non-overlapping assertions for
value-window functions:

- named `RANGE` frames inherit their window `ORDER BY`;
- inline base-window `GROUPS` frames inherit their base `ORDER BY`;
- named value `RANGE` frames without `ORDER BY` reject with the SQL parser
  diagnostic;
- direct value `RANGE` frames without `ORDER BY` reject with the direct-query
  diagnostic.

Focused verification after edit:

```bash
php tools/run-tests.php lanes/libsqlite/tests/SQLiteWindowGroupsRangeCurrentNext18Test.php
# 1 test files, 91 assertions, 0 failures
```

Dependency closure: no new support component is needed; this reuses existing
`SQLiteSelectSql` and `SQLiteSelectQuery` window frame validation.

Non-overlap: this stays within the assigned window RANGE/GROUPS root-gate
surface and does not touch suite denominator burnup, runner maps, source API
neutralization, dashboard files, or accepted VFS/WAL/B-tree/JSON planner
clusters.
