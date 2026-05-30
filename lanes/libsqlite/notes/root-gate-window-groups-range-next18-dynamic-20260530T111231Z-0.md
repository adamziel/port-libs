# Root Gate Window Groups Range Current Next18

Micro-slice: `root-gate-window-groups-range-next18-dynamic-20260530T111231Z-0`

Assigned root blocker:
`upstream corpus window groups range current next18 rejects frame without order`.

Before edit on this worktree, the assigned blocker was already green on the
current base:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteWindowGroupsRangeCurrentNext18Test.php
# 1 test files, 67 assertions, 0 failures
```

Change made: added direct-query `RANGE` frame-without-`ORDER BY` message
coverage alongside the existing direct-query `GROUPS` guard. This preserves the
accepted root-gate behavior and makes the sibling direct-query error surface
explicit without weakening assertions or deleting coverage.

After edit:

```sh
php -l lanes/libsqlite/tests/SQLiteWindowGroupsRangeCurrentNext18Test.php
# No syntax errors detected in lanes/libsqlite/tests/SQLiteWindowGroupsRangeCurrentNext18Test.php

php tools/run-tests.php lanes/libsqlite/tests/SQLiteWindowGroupsRangeCurrentNext18Test.php
# 1 test files, 68 assertions, 0 failures

php tools/run-tests.php lanes/libsqlite/tests/SQLiteWindowGroupsRangeCurrentNext18Test.php lanes/libsqlite/tests/SQLiteWindowPeerRangeCurrentNext16Test.php lanes/libsqlite/tests/SQLiteWindowRangeGroupsExcludeNext10Test.php lanes/libsqlite/tests/SQLiteWindowFrameBoundaryCorpusTest.php lanes/libsqlite/tests/SQLiteWindowFrameExcludeFilterCurrentSourceNextTest.php lanes/libsqlite/tests/SQLiteWindowExcludeFilterCorpusTest.php
# 6 test files, 336 assertions, 0 failures

git diff --check -- lanes/libsqlite
# clean
```

Root harness: not run - isolated micro-slice.

Dependency closure: no new support component needed; this reuses the existing
lane-local `SQLiteSelectQuery` window-frame validation.

Non-overlap: this only reinforces the existing current-next18 window
`RANGE`/`GROUPS` no-`ORDER BY` guard and avoids suite-evidence dependency
closure, STAT4, VFS, WAL, B-tree, JSON, and numbered-production cleanup
surfaces.
