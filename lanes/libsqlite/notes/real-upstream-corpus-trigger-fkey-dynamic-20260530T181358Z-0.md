# real-upstream-corpus-trigger-fkey-dynamic-20260530T181358Z-0

Base accepted HEAD: `a9928e604a7d849ecf8aa28f83049e71a24f4b05`.

Owned upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey2.test`
- Sections covered: `fkey2-1` simple immediate/deferred checks, `fkey2-4` cascading actions with recursive triggers disabled, `fkey2-9` SET NULL/SET DEFAULT actions, `fkey2-11` CASCADE delete actions, `fkey2-12` RESTRICT immediacy, and `fkey2-20` statement conflict policy not overriding FK actions.

Patch summary:

- Extended `SQLiteRealUpstreamTriggerFkeyDynamicTest.php` with a broad generic application matrix over upstream FK update/delete actions.
- Added 400 distinct update-action PASS cases, 400 distinct delete-action PASS cases, 120 deferred statement-check PASS cases, and 80 RESTRICT-immediacy PASS cases.
- No production source change was needed; existing `SQLiteTriggerForeignKeyReturningPlan` already models the required FK action, trigger side-effect, RETURNING, and deferred violation behavior.

Focused evidence:

- Before: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicTest.php`
  - `1 test files, 1131 assertions, 0 failures`
  - 89 PASS lines.
- After: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicTest.php`
  - `1 test files, 12911 assertions, 0 failures`
  - 1089 PASS lines.
- Honest delta: `+1000` focused PASS lines and `+11780` behavior assertions.

Non-overlap:

- This batch extends the already accepted trigger/FK dynamic file rather than adding generated metadata rows.
- It does not repeat date/expr/window/VFS/JSON/B-tree surfaces from the current accepted lane status.
- It uses generic `settings` row names only and adds no WordPress-specific source API.

Dependency closure:

- No new support component is needed. The batch reuses the existing bounded trigger/FK/RETURNING plan helper.

Next larger batch:

- Continue with non-overlapping `trigger1.test`/`trigger2.test` parser and runtime trigger cases, or add a broader simple-schema `fkey2.test` executor matrix if it can again clear the 1000-PASS floor.
