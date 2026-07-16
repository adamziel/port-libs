# Root Gate Window GROUPS/RANGE Next18

Assigned root-gate failure:

```bash
php tools/run-tests.php lanes/libsqlite/tests/SQLiteWindowGroupsRangeCurrentNext18Test.php
```

Before fix result on accepted `e91c5c4f41809ba4851c9164c6b6453b769e4519`:
`1 test files, 46 assertions, 1 failures`; the failing assertion was
`upstream corpus window groups range current next18 rejects frame without order`
because `SQLiteSelectQuery` executed an explicit `GROUPS` aggregate frame
without a window `ORDER BY`.

Fix: `SQLiteSelectQuery` now rejects explicit aggregate `RANGE`/`GROUPS` frames
without window `ORDER BY`, preserving default aggregate windows that omit an
explicit frame.

Dependency closure: no new support component needed; this reuses the existing
SELECT SQL window parser and row-array window aggregate executor.

Non-overlap: this does not repeat suffix consolidation, JSON table, VFS/WAL,
B-tree, or suite-evidence work. It is limited to the current root-gate
window-frame validation blocker.
