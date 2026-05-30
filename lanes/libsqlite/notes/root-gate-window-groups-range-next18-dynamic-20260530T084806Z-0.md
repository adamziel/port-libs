# Root Gate Window GROUPS/RANGE Next18 Dynamic

Assigned root-gate failure:

```bash
php tools/run-tests.php lanes/libsqlite/tests/SQLiteWindowGroupsRangeCurrentNext18Test.php
```

Current isolated base `b8bd6077844c0082ed784bbf6571ef8bd54ad7c1` does not
reproduce the assigned failure. The focused command passes at `1 test files,
52 assertions, 0 failures`, including
`upstream corpus window groups range current next18 rejects frame without order`.

Prior accepted failing evidence remains recorded in
`root-gate-window-groups-range-next18-dynamic-20260530T065318Z-0.md`: on
`e91c5c4f41809ba4851c9164c6b6453b769e4519`, this same focused command failed
at `1 test files, 46 assertions, 1 failures` because the direct aggregate
window executor allowed an explicit `GROUPS` frame without window `ORDER BY`.
The current source already contains the behavior fix in `SQLiteSelectQuery`.

Verification on this base:

```bash
php tools/run-tests.php lanes/libsqlite/tests/SQLiteWindowGroupsRangeCurrentNext18Test.php
# 1 test files, 52 assertions, 0 failures

php tools/run-tests.php lanes/libsqlite/tests/SQLiteWindowGroupsRangeCurrentNext18Test.php lanes/libsqlite/tests/SQLiteWindowPeerRangeCurrentNext16Test.php lanes/libsqlite/tests/SQLiteWindowRangeGroupsExcludeNext10Test.php lanes/libsqlite/tests/SQLiteWindowFrameBoundaryCorpusTest.php lanes/libsqlite/tests/SQLiteWindowFrameExcludeFilterCurrentSourceNextTest.php lanes/libsqlite/tests/SQLiteWindowExcludeFilterCorpusTest.php
# 6 test files, 320 assertions, 0 failures

php -l lanes/libsqlite/src/SQLiteSelectQuery.php
# No syntax errors detected in lanes/libsqlite/src/SQLiteSelectQuery.php

php -l lanes/libsqlite/src/SQLiteSelectSql.php
# No syntax errors detected in lanes/libsqlite/src/SQLiteSelectSql.php
```

Dependency closure: no new support component is needed; the current behavior
uses the existing SELECT SQL window parser and row-array window aggregate
executor.

Non-overlap: no source or status counters were changed. This note is limited to
the current root-gate window-frame validation assignment and does not repeat
suite evidence, JSON table, VFS/WAL, B-tree, or suffix-consolidation work.
