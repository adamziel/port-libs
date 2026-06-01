# real-upstream-corpus-trigger-fkey-dynamic-20260601T070159Z-0

Base accepted HEAD: `cc9294ac19877407e3f202dbdfd54b6a9a8fb67d`.

## Upstream Source Truth

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/triggerC.test`
- Ported sections: `triggerC-12.1` and `triggerC-12.2`.

## Behavior

- The upstream case creates table `t1`, trigger `tr1`, and verifies the schema
  catalog contains both rows.
- While a `SELECT * FROM t1` cursor is active, the same connection executes
  `DROP TRIGGER tr1` when the scan reaches `a == 3`.
- The active table scan completes in source row order, the table catalog row is
  preserved, the trigger catalog row is removed, and the schema cookie advances.

## Changed Lane Files

- `lanes/libsqlite/src/SQLiteDynamicTriggerForeignKeyPlan.php`
- `lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicTriggerCActiveScanDrop20260601Test.php`
- `lanes/libsqlite/lane-status.json`
- `lanes/libsqlite/notes/real-upstream-corpus-trigger-fkey-dynamic-20260601T070159Z-0.md`

## Focused Evidence

- `php -l lanes/libsqlite/src/SQLiteDynamicTriggerForeignKeyPlan.php`
  - `No syntax errors detected in lanes/libsqlite/src/SQLiteDynamicTriggerForeignKeyPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicTriggerCActiveScanDrop20260601Test.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicTriggerCActiveScanDrop20260601Test.php`
- `php -r '$tests = require "lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicTriggerCActiveScanDrop20260601Test.php"; echo count($tests), PHP_EOL;'`
  - `7992`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicTriggerCActiveScanDrop20260601Test.php`
  - `1 test files, 7996 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - `1 test files, 5 assertions, 0 failures`
- `php -r '$data = json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true); if (!is_array($data)) { fwrite(STDERR, json_last_error_msg() . PHP_EOL); exit(1); } echo "lane-status.json valid", PHP_EOL;'`
  - `lane-status.json valid`
- `git diff --check -- lanes/libsqlite`
  - passed with no output

Root harness: not run - isolated micro-slice.

## Countability

- Focused selected movement: `+7992` behavior PASS cases and `7996`
  assertions from real upstream `triggerC.test` active-scan trigger-drop cases.
- `lane-status.json` updates `phpPass` from `5623914` to `5631906`.
- Mapped denominator remains `1589 / 1589`; this is behavior growth over an
  already mapped upstream file.

## Non-Overlap

This covers the distinct `triggerC-12.1..12.2` active table-scan catalog-change
case. It does not repeat accepted triggerC default values (`triggerC-11`),
indexed recursive delete cascade (`triggerC-9`), before-trigger self mutation
(`triggerC-10`), recursion depth (`triggerC-13`), trigger-program constant loop
(`triggerC-14`), late diagnostics (`triggerC-16..17`), trigger6 evaluate-once,
trigger8 large-body, triggerupfrom, e_droptrigger, fkey action/deferred batches,
VFS, WAL, B-tree, PRAGMA, JSON, or SELECT corpus coverage.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PHP
trigger/FK dynamic planner surface and the hydrated SQLite upstream checkout as
source truth.
