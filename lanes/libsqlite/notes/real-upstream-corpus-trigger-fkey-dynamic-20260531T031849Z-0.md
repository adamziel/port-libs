# real-upstream-corpus-trigger-fkey-dynamic-20260531T031849Z-0

Base accepted HEAD: `582d5b219b619868bb38159464dc8e8768230ba8`.

Added a focused upstream-backed trigger/FK dynamic slice to the existing
`SQLiteUpstreamTriggerFkeyDynamicRealCorpusTest.php` file.

Upstream source sections:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/triggerG.test`
  - `triggerG-100/110`: recursive trigger inserts into the same table and
    SELECTs through an `IN` predicate.
  - `triggerG-200`: recursive trigger with two self-joined source cursors and
    separate `IN` predicates.
  - `triggerG-300/310`: trigger body reports oversized hex literal error at
    execution.
  - `triggerG-400/405/410`: INSTEAD OF DELETE trigger on a view can read
    `old` rows.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey8.test`
  - `fkey8-1.*`: `uses_stmt_journal()` expectations for dynamic foreign-key
    actions, trigger side effects, grandchild actions, bound updates, and
    conflict modifiers.

Focused movement:

- Added `130` focused PASS cases.
- Focused command: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteUpstreamTriggerFkeyDynamicRealCorpusTest.php`
- Result: `1 test files, 1349 assertions, 0 failures`.

Non-overlap:

- Does not repeat the existing fkey2 deferred transaction replay, fkey7 read
  dependency/zeroblob/OR FAIL coverage, trigger2 row timing coverage, triggerE
  variable rejection, or fkey8 deferred cascade/update cascade behavior.
- Does not add WordPress-specific libsqlite API names or source behavior.

Dependency closure:

- No new support component is needed. This reuses the existing lane-local
  upstream trigger/FK dynamic plan helper and PHP TestRunner harness.

Root harness:

- Not run - isolated micro-slice.
