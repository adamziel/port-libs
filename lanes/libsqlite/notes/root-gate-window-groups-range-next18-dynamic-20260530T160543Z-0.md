# Root Gate Window Groups Range Next18 Dynamic

## Scope

Assigned root-gate failure family:
`SQLiteWindowGroupsRangeCurrentNext18Test.php` /
`upstream corpus window groups range current next18 rejects frame without order`.

The named failure did not reproduce on accepted base
`8bf0d9f81b29a5601901bb34dfd730670ed39bbc`; the focused test was already
green at `87` assertions. This slice adds a behavior-backed dynamic parser
hardening on the same GROUPS/RANGE frame surface: SQL `--` and `/* ... */`
comments are stripped before SELECT planning while quoted string and quoted
identifier text is preserved. This keeps commented `ORDER BY ... GROUPS/RANGE`
frames executable and keeps commented `RANGE/GROUPS` frames without an ORDER BY
rejected with the same root-gate diagnostic.

## Evidence

Before edit:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteWindowGroupsRangeCurrentNext18Test.php
# 1 test files, 87 assertions, 0 failures
```

After edit:

```sh
php -l lanes/libsqlite/src/SQLiteSelectSql.php
# No syntax errors detected in lanes/libsqlite/src/SQLiteSelectSql.php

php -l lanes/libsqlite/tests/SQLiteWindowGroupsRangeCurrentNext18Test.php
# No syntax errors detected in lanes/libsqlite/tests/SQLiteWindowGroupsRangeCurrentNext18Test.php

php tools/run-tests.php lanes/libsqlite/tests/SQLiteWindowGroupsRangeCurrentNext18Test.php
# 1 test files, 90 assertions, 0 failures

php tools/run-tests.php lanes/libsqlite/tests/SQLiteWindowGroupsRangeCurrentNext18Test.php lanes/libsqlite/tests/SQLiteWindowPeerRangeCurrentNext16Test.php lanes/libsqlite/tests/SQLiteWindowRangeGroupsExcludeNext10Test.php lanes/libsqlite/tests/SQLiteWindowFrameBoundaryCorpusTest.php lanes/libsqlite/tests/SQLiteWindowFrameExcludeFilterCurrentSourceNextTest.php lanes/libsqlite/tests/SQLiteWindowExcludeFilterCorpusTest.php lanes/libsqlite/tests/SQLiteWindowGroupsRangeNoOrderGuardTest.php
# 7 test files, 366 assertions, 0 failures
```

`SQLiteNoWordPressSpecificApiTest.php` is not present in this worktree, so the
API guard was not runnable here.

## Dependency Closure

No new support component is needed. The slice reuses the existing
`SQLiteSelectSql` planner/parser and focused window-family tests.

## Non-Overlap

This does not repeat prior no-ORDER-BY assertion-only coverage: it fixes
commented SQL input handling before the window frame parser, then verifies the
same ORDER-BY requirement still holds after comments are removed.
