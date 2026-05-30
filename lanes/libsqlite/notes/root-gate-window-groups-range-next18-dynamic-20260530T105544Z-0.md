# root-gate-window-groups-range-next18-dynamic-20260530T105544Z-0

Assigned root blocker:
`upstream corpus window groups range current next18 rejects frame without order`.

Reproduction on this accepted worktree:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteWindowGroupsRangeCurrentNext18Test.php
```

Result before the additive patch: the assigned blocker was already green in
this worktree at `1 test files, 65 assertions, 0 failures`.

Patch:
- Added two regression assertions for named `WINDOW` definitions whose
  `GROUPS`/`RANGE` frames inherit an `ORDER BY` from the window definition.
- This keeps the root-gate no-ORDER rejection covered while proving the
  adjacent valid named-window path is not over-rejected.

Verification after the patch:

```sh
php -l lanes/libsqlite/tests/SQLiteWindowGroupsRangeCurrentNext18Test.php
# No syntax errors detected in lanes/libsqlite/tests/SQLiteWindowGroupsRangeCurrentNext18Test.php

php tools/run-tests.php lanes/libsqlite/tests/SQLiteWindowGroupsRangeCurrentNext18Test.php
# 1 test files, 67 assertions, 0 failures

php tools/run-tests.php lanes/libsqlite/tests/SQLiteWindowGroupsRangeCurrentNext18Test.php lanes/libsqlite/tests/SQLiteWindowPeerRangeCurrentNext16Test.php lanes/libsqlite/tests/SQLiteWindowRangeGroupsExcludeNext10Test.php lanes/libsqlite/tests/SQLiteWindowFrameBoundaryCorpusTest.php lanes/libsqlite/tests/SQLiteWindowFrameExcludeFilterCurrentSourceNextTest.php lanes/libsqlite/tests/SQLiteWindowExcludeFilterCorpusTest.php
# 6 test files, 335 assertions, 0 failures

git diff --check -- lanes/libsqlite
# passed
```

Dependency closure: no new support component needed; this reuses existing
`SQLiteSelectSql` named-window expansion and `SQLiteSelectQuery` window frame
execution.

Non-overlap: avoids suite-evidence row preservation work and does not repeat
window frame implementation changes; it only hardens the assigned
window-groups-range root-gate family with named-window ORDER inheritance
coverage.
