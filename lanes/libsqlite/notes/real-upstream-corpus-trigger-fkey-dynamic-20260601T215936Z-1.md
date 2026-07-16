# Real Upstream Corpus Trigger/FK Dynamic 20260601T215936Z-1

## Scope

- Lane: `libsqlite`
- Base accepted HEAD: `7b6b747e54eb6630d159571cf9785d0872a67c29`
- Upstream source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/triggerC.test`
- Ported upstream scenarios: `triggerC-6.1`, `triggerC-6.2`, `triggerC-6.3`

This slice owns only the `triggerC.test` recursive trigger PRAGMA state block:
`PRAGMA recursive_triggers` reads report the current connection state, `PRAGMA
recursive_triggers = off` disables it, and `PRAGMA recursive_triggers = on`
restores it. The new plan surface records read rows, state transitions, toggle
tokens, history rows, and dependencies for dynamic upstream-shaped sequences.

## Countability

- Adds `+2006` focused TestRunner PASS cases.
- Focused assertion evidence: `1 test files, 2287 assertions, 0 failures`.
- `phpPass`: `6278617 -> 6280623`.
- Mapped upstream coverage remains `1589 / 1589`.

## Verification

- `php -l lanes/libsqlite/src/SQLiteDynamicTriggerForeignKeyPlan.php`
  - `No syntax errors detected in lanes/libsqlite/src/SQLiteDynamicTriggerForeignKeyPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicTriggerCRecursivePragma20260601Test.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicTriggerCRecursivePragma20260601Test.php`
- `php -r '$tests = require "lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicTriggerCRecursivePragma20260601Test.php"; echo count($tests), PHP_EOL;'`
  - `2006`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicTriggerCRecursivePragma20260601Test.php`
  - `1 test files, 2287 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - `1 test files, 8 assertions, 0 failures`
- `php tools/run-tests.php $(rg --files lanes/libsqlite/tests | rg 'SQLiteRealUpstreamTriggerFkeyDynamicTriggerC.*Test\.php$' | sort)`
  - `12 test files, 88017 assertions, 0 failures`
- `php -r '$json = file_get_contents("lanes/libsqlite/lane-status.json"); json_decode($json, true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'`
  - `lane-status json ok`
- `git diff --check -- lanes/libsqlite`
  - clean, no output

## Non-Overlap

This does not repeat accepted TriggerC trigger body, recursive depth,
conflict/rollback, constant-loop, active-scan DROP, insert-depth cutoff, indexed
delete cascade, rowid mutation, default-values, or affinity-timing slices. It
also avoids existing `e_fkey`, `fkey1`, `fkey2`, `fkey6`, `fkey8`, `trigger6`,
`trigger7`, `trigger8`, and `triggerupfrom` coverage. The owned upstream block
is only `triggerC-6.1..6.3`.

## Dependency Closure

No new support component is needed. The patch reuses the existing
`SQLiteDynamicTriggerForeignKeyPlan` dynamic plan surface and the hydrated
upstream SQLite cache for source-truth checks. Root harness status:
`not run - isolated micro-slice`.
