# real-upstream-corpus-trigger-fkey-dynamic-20260531T012622Z-0

Source base: `af20380a278ad54b2ad38b5d180ded7ec9aac2e7`

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey2.test`
- Section `fkey2-4.1..4.4`: recursive FK actions are allowed even when `PRAGMA recursive_triggers = off`, while ordinary user trigger recursion follows the pragma.

Implemented behavior:

- Added `SQLiteDynamicTriggerForeignKeyPlan::fkey2RecursiveCascadeIgnoresRecursiveTriggerPragma()`.
- The model deletes the same tree through a foreign-key cascade path and an ordinary trigger path.
- FK cascades always visit descendant rows.
- Ordinary trigger recursion visits only direct children when recursive triggers are disabled, and visits descendants when enabled.
- Malformed trigger/FK tree rows are rejected before producing a plan.

Focused evidence:

- `php -l lanes/libsqlite/src/SQLiteDynamicTriggerForeignKeyPlan.php`
  - `No syntax errors detected`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicRecursiveCascadePragmaTest.php`
  - `No syntax errors detected`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicRecursiveCascadePragmaTest.php`
  - `1 test files, 4325 assertions, 0 failures`

Expected dashboard movement:

- `phpPass`: `1493978 -> 1498303` (`+4325` focused PASS lines).
- Mapped denominator: unchanged at `1589 / 1589`.

Non-overlap:

- This does not repeat existing fkey2 deferred transaction, savepoint, statement-counter reset, fkey6 defer-pragma, trigger6 expression, or count_changes coverage.
- This specifically owns upstream `fkey2.test` `fkey2-4.1..4.4`, the recursive-FK-action versus ordinary-recursive-trigger pragma distinction.

Dependency closure:

- No new support component is needed.
- The slice reuses the existing in-repo dynamic trigger/FK plan surface and adds a bounded native PHP helper for the upstream behavior.
