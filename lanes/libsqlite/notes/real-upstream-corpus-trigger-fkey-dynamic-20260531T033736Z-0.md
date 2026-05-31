# real-upstream-corpus-trigger-fkey-dynamic-20260531T033736Z-0

Status: ready focused real-upstream trigger/FK dynamic behavior slice.

Upstream source:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/trigger6.test`

Ported scenarios:

- `trigger6-1.1` / `trigger6-1.2`: INSERT expression with side-effecting
  counter is evaluated once, and BEFORE INSERT trigger `new.y` sees the same
  value as the inserted row.
- `trigger6-1.3` / `trigger6-1.4`: INSERT expression composition
  `counter(...)+4` is evaluated once, and trigger `NEW.*` reuses the statement
  value.
- `trigger6-1.5` / `trigger6-1.6`: UPDATE expression with side-effecting
  counter is evaluated once, and BEFORE UPDATE trigger `new.y` sees the same
  updated value.

Changed files:

- `lanes/libsqlite/src/SQLiteUpstreamTriggerFkeyDynamicPlan.php`
- `lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicTrigger6EvaluateOnceTest.php`
- `lanes/libsqlite/lane-status.json`
- `lanes/libsqlite/notes/real-upstream-corpus-trigger-fkey-dynamic-20260531T033736Z-0.md`

Focused evidence:

- `php -l lanes/libsqlite/src/SQLiteUpstreamTriggerFkeyDynamicPlan.php`
  - PASS: no syntax errors.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicTrigger6EvaluateOnceTest.php`
  - PASS: no syntax errors.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicTrigger6EvaluateOnceTest.php`
  - PASS: `1 test files, 2245 assertions, 0 failures`.
  - PASS-line count: `2081`.

Status delta:

- `phpPass`: `1878158 -> 1880239` if accepted, based on the focused PASS-line
  count above.
- `benchmarkDenominator.mapped`: unchanged at `1589 / 1589`; this is behavior
  growth against already mapped real upstream trigger inventory.

Non-overlap:

- This slice does not repeat accepted fkey1/fkey2/fkey5/fkey6/fkey7/fkey8,
  e_fkey, trigger1, trigger2, trigger3 RAISE, trigger4 view routing,
  trigger5 undo, trigger7/trigger8 large-body, trigger9 OLD rows, triggerA
  view WHERE propagation, triggerB wide recursive queues, triggerC/D/E/F/G,
  triggerupfrom, FK action journals, trigger count_changes, recursive trigger
  ONCE, or recent broad trigger/FK dynamic batches.
- The new surface is specifically upstream `trigger6.test` expression
  evaluation-once behavior for side-effecting INSERT/UPDATE expressions as
  observed by trigger `NEW.*` rows.

Dependency closure:

- No new support component is needed. This reuses the existing native trigger/FK
  dynamic plan helper and PHP TestRunner harness.

Root harness:

- Not run - isolated micro-slice.
