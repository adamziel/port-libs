# Root Gate Window Groups Range Current Next18

Micro-slice: `root-gate-window-groups-range-next18-dynamic-20260530T122352Z-0`

Assigned root blocker:
`upstream corpus window groups range current next18 rejects frame without order`.

Before edit on this worktree, the assigned blocker was already green on the
current base, but inline base-window inheritance still falsely rejected a
`GROUPS` frame when the named base supplied `ORDER BY`:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteWindowGroupsRangeCurrentNext18Test.php
# 1 test files, 78 assertions, 0 failures
```

Change made: `SQLiteSelectSql` now expands `OVER (base_window GROUPS/RANGE
...)` by merging the named base-window definition before frame validation.
This keeps `RANGE`/`GROUPS` frames rejected when neither the inline clause nor
the base window supplies `ORDER BY`, while allowing the upstream-supported
base-window inheritance path.

After edit:

```sh
php -l lanes/libsqlite/src/SQLiteSelectSql.php
# No syntax errors detected in lanes/libsqlite/src/SQLiteSelectSql.php

php -l lanes/libsqlite/tests/SQLiteWindowGroupsRangeCurrentNext18Test.php
# No syntax errors detected in lanes/libsqlite/tests/SQLiteWindowGroupsRangeCurrentNext18Test.php

php tools/run-tests.php lanes/libsqlite/tests/SQLiteWindowGroupsRangeCurrentNext18Test.php
# 1 test files, 80 assertions, 0 failures

php tools/run-tests.php lanes/libsqlite/tests/SQLiteWindowGroupsRangeCurrentNext18Test.php lanes/libsqlite/tests/SQLiteWindowPeerRangeCurrentNext16Test.php lanes/libsqlite/tests/SQLiteWindowRangeGroupsExcludeNext10Test.php lanes/libsqlite/tests/SQLiteWindowFrameBoundaryCorpusTest.php lanes/libsqlite/tests/SQLiteWindowFrameExcludeFilterCurrentSourceNextTest.php lanes/libsqlite/tests/SQLiteWindowExcludeFilterCorpusTest.php
# 6 test files, 348 assertions, 0 failures

php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoWordPressSpecificApiTest.php
# 1 test files, 3 assertions, 0 failures

git diff --check -- lanes/libsqlite
# clean
```

Root harness: not run - isolated micro-slice.

Dependency closure: no new support component needed; this reuses the existing
lane-local `SQLiteSelectSql` named-window parser and window-frame validation.

Non-overlap: this is limited to inline base-window inheritance for the current
next18 `RANGE`/`GROUPS` no-`ORDER BY` root-gate family. It avoids suite
evidence, STAT4, VFS, WAL, B-tree, JSON, and numbered-production cleanup
surfaces.
