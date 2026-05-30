# Root Gate Window Groups Range Current Next18

Micro-slice: `root-gate-window-groups-range-next18-dynamic-20260530T120113Z-0`

Assigned root blocker:
`upstream corpus window groups range current next18 rejects frame without order`.

Before edit on this worktree, the assigned blocker was already green on the
current base:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteWindowGroupsRangeCurrentNext18Test.php
# 1 test files, 75 assertions, 0 failures
```

Change made: added SQL-level `RANGE` frame-without-`ORDER BY` diagnostic
coverage alongside the existing SQL-level `GROUPS` diagnostic guard. This keeps
the accepted root-gate behavior explicit for both frame units without weakening
assertions or deleting coverage.

After edit:

```sh
php -l lanes/libsqlite/tests/SQLiteWindowGroupsRangeCurrentNext18Test.php
# No syntax errors detected in lanes/libsqlite/tests/SQLiteWindowGroupsRangeCurrentNext18Test.php

php tools/run-tests.php lanes/libsqlite/tests/SQLiteWindowGroupsRangeCurrentNext18Test.php
# 1 test files, 76 assertions, 0 failures
```

Root harness: not run - isolated micro-slice.

Dependency closure: no new support component needed; this reuses the existing
lane-local `SQLiteSelectSql` and `SQLiteSelectQuery` window-frame validation.

Non-overlap: this only reinforces the current-next18 window `RANGE`/`GROUPS`
no-`ORDER BY` guard and avoids suite-evidence dependency closure, STAT4, VFS,
WAL, B-tree, JSON, and numbered-production cleanup surfaces.
