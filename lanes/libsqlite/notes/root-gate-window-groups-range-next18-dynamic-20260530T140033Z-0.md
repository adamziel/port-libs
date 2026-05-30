# Window GROUPS/RANGE Next18 Root Gate

Micro-slice: `root-gate-window-groups-range-next18-dynamic-20260530T140033Z-0`

Before change:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteWindowGroupsRangeCurrentNext18Test.php
1 test files, 80 assertions, 0 failures
```

The assigned root-gate failure was already fixed on this accepted base, including
`upstream corpus window groups range current next18 rejects frame without order`.

Change made:

- Kept the existing RANGE/GROUPS no-ORDER-BY rejection behavior.
- Narrowed direct value-window frame validation so `ROWS` frames without
  `ORDER BY` remain valid while `RANGE`/`GROUPS` frames still reject.
- Added SQL text and direct-query regression coverage for `first_value()` and
  `last_value()` over `ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING` without
  `ORDER BY`.
- Added a direct-query message assertion for value `GROUPS` frames without
  `ORDER BY`.

After change:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteWindowGroupsRangeCurrentNext18Test.php
1 test files, 84 assertions, 0 failures
```

Dependency closure: no new support component needed; the slice reuses the
existing row-array SELECT SQL/direct-query window executor and frame validator.

Non-overlap: this stays within the existing window RANGE/GROUPS root-gate
family, avoids suite-evidence metadata, WAL/VFS/B-tree/JSON/planner surfaces,
and does not introduce WordPress-specific declarations or examples.
