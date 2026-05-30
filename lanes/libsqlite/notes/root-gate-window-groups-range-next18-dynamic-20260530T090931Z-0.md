# Root Gate Window GROUPS/RANGE Next18 Dynamic

Assigned root-gate failure:

```bash
php tools/run-tests.php lanes/libsqlite/tests/SQLiteWindowGroupsRangeCurrentNext18Test.php
```

Before this patch on isolated base `a837352336f51b8685b8815b54fd2746fd89f74e`,
the focused command did not reproduce the historical root failure and passed at
`1 test files, 54 assertions, 0 failures`, including
`upstream corpus window groups range current next18 rejects frame without order`.

Historical failing evidence is preserved in
`root-gate-window-groups-range-next18-dynamic-20260530T065318Z-0.md`: on
`e91c5c4f41809ba4851c9164c6b6453b769e4519`, this focused command failed at
`1 test files, 46 assertions, 1 failures` because the direct aggregate window
executor allowed an explicit `GROUPS` frame without window `ORDER BY`.

Patch: add direct `SQLiteSelectQuery` coverage for the same root-gate invariant
so callers that bypass SQL text parsing also prove explicit aggregate
`GROUPS`/`RANGE` frames require window `ORDER BY`.

After this patch:

```bash
php tools/run-tests.php lanes/libsqlite/tests/SQLiteWindowGroupsRangeCurrentNext18Test.php
# 1 test files, 56 assertions, 0 failures

php tools/run-tests.php lanes/libsqlite/tests/SQLiteWindowGroupsRangeCurrentNext18Test.php lanes/libsqlite/tests/SQLiteWindowPeerRangeCurrentNext16Test.php lanes/libsqlite/tests/SQLiteWindowRangeGroupsExcludeNext10Test.php lanes/libsqlite/tests/SQLiteWindowFrameBoundaryCorpusTest.php lanes/libsqlite/tests/SQLiteWindowFrameExcludeFilterCurrentSourceNextTest.php lanes/libsqlite/tests/SQLiteWindowExcludeFilterCorpusTest.php
# 6 test files, 324 assertions, 0 failures

php -l lanes/libsqlite/tests/SQLiteWindowGroupsRangeCurrentNext18Test.php
# No syntax errors detected in lanes/libsqlite/tests/SQLiteWindowGroupsRangeCurrentNext18Test.php

git diff --check -- lanes/libsqlite
# clean
```

Dependency closure: no new support component is needed; the coverage reuses the
existing SELECT SQL window parser and row-array window aggregate executor.

Non-overlap: this is limited to current next18 window-frame validation and does
not touch suite evidence, JSON table, VFS/WAL, B-tree, suffix consolidation, or
dashboard/progress files.
