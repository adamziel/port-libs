## real-upstream-corpus-trigger-fkey-dynamic trigger1 create diagnostics

- Base accepted HEAD: `9c30c680e4b44fbeb2fc11612b28622bb7d8e322`.
- Upstream source: `/home/claude/port-libs/.upstream-cache/libsqlite/test/trigger1.test`.
- Ported scenarios: `trigger1-1.1.1`, `trigger1-1.1.2`, `trigger1-1.1.3`, `trigger1-1.2.0`, `trigger1-1.2.1`, `trigger1-1.2.2`, `trigger1-1.2.3`, and `trigger1-1.9`.
- Non-overlap: existing dynamic trigger/FK coverage already owns trigger1 lifecycle `1.2..1.8`, statement preservation `1.10..1.11`, target class `1.12..1.14`, triggerE variable handling, fkey2 authorizer, trigger7/8/9, temptrigger, and triggerupfrom. This slice focuses on create-time diagnostics for missing targets, statement triggers, duplicate names, `IF NOT EXISTS`, and system-table targets.
- Focused assertion movement: `SQLiteRealUpstreamTriggerFkeyDynamicTrigger1CreateDiagnosticsTest.php` passes with `2309` assertions.
- Related guard: trigger/FK dynamic corpus plus triggerE related files pass with `28922` assertions.
- Dependency closure: no new support component needed; the slice reuses lane-local schema catalog and trigger diagnostic planning helpers.

Verification:

```text
php -l lanes/libsqlite/src/SQLiteDynamicTriggerForeignKeyPlan.php
No syntax errors detected in lanes/libsqlite/src/SQLiteDynamicTriggerForeignKeyPlan.php

php -l lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicTrigger1CreateDiagnosticsTest.php
No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicTrigger1CreateDiagnosticsTest.php

php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicTrigger1CreateDiagnosticsTest.php
1 test files, 2309 assertions, 0 failures

php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicTrigger1CreateDiagnosticsTest.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicCorpusTest.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyTriggerEDynamicCorpusTest.php
3 test files, 28922 assertions, 0 failures
```
