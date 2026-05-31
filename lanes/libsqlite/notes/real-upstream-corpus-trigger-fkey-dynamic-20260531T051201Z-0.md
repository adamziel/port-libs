# real-upstream-corpus-trigger-fkey-dynamic-20260531T051201Z-0

Status: ready for integration from accepted base `7174979f2808c9ccf08c3331545660695c77e192`.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_fkey.test`
- Ported sections: `e_fkey-1`, `e_fkey-2.1..2.3`, and `e_fkey-3.1..3.5`.

Behavior added:

- `SQLiteDynamicTriggerForeignKeyPlan::foreignKeyCapabilityModePlan()` models the upstream capability split:
  full trigger+foreign-key support parses and enforces `ON UPDATE CASCADE`, OMIT_TRIGGER parses `REFERENCES` and exposes `foreign_key_list` rows but does not enforce actions, and OMIT_FOREIGN_KEY rejects FK action syntax while leaving FK pragmas empty.
- `SQLiteRealUpstreamTriggerFkeyDynamicCapabilityModeTest.php` adds 1,000 dynamic generic application cases plus source-citation and malformed-input guards.

Verification:

- `php -l lanes/libsqlite/src/SQLiteDynamicTriggerForeignKeyPlan.php` passed.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicCapabilityModeTest.php` passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicCapabilityModeTest.php` passed: `1 test files, 14681 assertions, 0 failures`, with `14672` PASS lines.
- `git diff --check -- lanes/libsqlite` passed.

Expected dashboard movement:

- `phpPass`: `2228722 -> 2243394` if accepted.
- `benchmarkDenominator.mapped`: unchanged at `1589 / 1589`; mapped inventory is already complete.

Non-overlap:

- This does not repeat accepted trigger/FK action matrices, `fkey1` replacement cascade, `fkey2` pragma/action/nocase/composite behavior, `fkey3` self-reference and parent-update actions, `fkey4` autocommit cleanup, `fkey5` foreign_key_check, `fkey6` deferred restrict, `fkey7` conflict/authorizer handling, `fkey8` action-journal/attached behavior, `fkey_malloc` retry atomicity, or trigger1-through-triggerG execution surfaces.
- The owned surface is specifically `e_fkey.test` compile/capability-mode behavior for `SQLITE_OMIT_TRIGGER` and `SQLITE_OMIT_FOREIGN_KEY` boundaries.

Dependency closure:

- No new support component is needed. This reuses the lane-local trigger/FK dynamic planner and the hydrated upstream SQLite test cache.
