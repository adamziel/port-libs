# real-upstream-corpus-trigger-fkey-dynamic-20260531T044054Z-0

Status: ready for integration from accepted base `0b81729d69877023d4b2607c8a1ffc5fac25bee0`.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey_malloc.test`
- Ported sections: `fkey_malloc-1` through `fkey_malloc-7`.
- Behavior: foreign-key cascades, deferred composite counters, SET DEFAULT / SET NULL actions, mismatch diagnostics, self-restrict/default behavior, and parent-drop checks remain atomic and retry cleanly across malloc-fault attempts.

Implementation:

- Added `SQLiteDynamicTriggerForeignKeyPlan::foreignKeyMallocRetryPlan()`.
- Added `SQLiteRealUpstreamTriggerFkeyDynamicMallocRetryTest.php` with 7 real upstream scenarios x 160 dynamic fault attempts plus source citation and malformed-input guards.

Verification:

- `php -l lanes/libsqlite/src/SQLiteDynamicTriggerForeignKeyPlan.php` passed.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicMallocRetryTest.php` passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicMallocRetryTest.php` passed: `1 test files, 18088 assertions, 0 failures`.
- `git diff --check -- lanes/libsqlite` passed.

Expected dashboard movement:

- `phpPass`: `2112679 -> 2130767` if accepted.
- `benchmarkDenominator.mapped`: unchanged at `1589 / 1589`; mapped inventory is already complete.

Non-overlap:

- This does not repeat accepted trigger/FK action/counter/view/drop-trigger coverage over `fkey1.test`, `fkey2.test`, `fkey3.test`, `fkey5.test`, `fkey6.test`, `fkey7.test`, `fkey8.test`, `trigger1.test`, `trigger2.test`, `trigger4.test`, `trigger5.test`, `trigger9.test`, `triggerA` through `triggerG`, `temptrigger.test`, or `triggerupfrom.test`.
- The owned surface is specifically `fkey_malloc.test` malloc-fault retry atomicity for FK actions and checks.

Dependency closure:

- No new support component is needed. This reuses the lane-local trigger/FK plan surface and models upstream malloc-fault boundaries as deterministic native PHP retry outcomes.
