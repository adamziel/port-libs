# Recursive Trigger Conflict Rollback Next14

Slice: `yield-sqlite-recursive-trigger-conflict-rollback-next14`

Status: focused PHP corpus growth for recursive trigger conflict `OR ROLLBACK`
semantics.

## Behavior

- Added `SQLiteRecursiveTriggerConflictRollbackPlan`, a bounded row-array
  executor for recursive `AFTER INSERT` triggers whose trigger body can hit a
  target-table UNIQUE conflict.
- Covers SQLite conflict-action differences for recursive trigger body DML:
  `ROLLBACK` restores the supplied transaction rollback image, `ABORT`
  restores the statement-start image, `FAIL` preserves prior statement rows,
  `IGNORE` skips the conflicting trigger row, and `REPLACE` updates the
  conflicting row.
- Added `application-recursive-trigger-conflict-rollback.php` for copied
  `wp_options` plugin import diagnostics without requiring `ext/sqlite`.

## Verification

```sh
php -l lanes/libsqlite/src/SQLiteRecursiveTriggerConflictRollbackPlan.php
php -l lanes/libsqlite/tests/SQLiteRecursiveTriggerConflictRollbackCorpusTest.php
php -l lanes/libsqlite/examples/application-recursive-trigger-conflict-rollback.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteRecursiveTriggerConflictRollbackCorpusTest.php
php lanes/libsqlite/examples/application-recursive-trigger-conflict-rollback.php
php -r 'json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane json ok\n";'
git diff --check -- lanes/libsqlite
```

Focused result:

```text
1 test files, 42 assertions, 0 failures
```

PASS-line delta: `+41`, so `lane-status.json` `phpPass` moves from `3796` to
`3837`. `benchmarkDenominator.mapped` is unchanged because this patch adds
focused PHP corpus coverage without claiming a newly hydrated upstream
inventory unit.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This avoids the accepted DML trigger conflict-inheritance corpus and recursive
trigger recursion corpus by focusing only on recursive trigger body UNIQUE
conflicts with `OR ROLLBACK` transaction-image restoration versus `ABORT`,
`FAIL`, `IGNORE`, and `REPLACE`. It also avoids accepted WAL/VFS rollback,
B-tree, JSON, SELECT SQL, Unicode GLOB, and suite-runner clusters.

Dependency closure: no new shared support component is needed; the slice reuses
lane-local row-array DML trigger modeling and copied Application option fixtures.
