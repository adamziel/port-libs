# real-upstream-corpus-trigger-fkey-dynamic-20260530T171612Z-0

- Slice: `real-upstream-corpus-trigger-fkey-dynamic-20260530T171612Z-0`
- Base accepted HEAD: `7ae2bafb13ace2a8edf7ffe53e4f4d55f2e4902f`
- Upstream source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey2.test`
- Scenario range: additional dynamic coverage for `fkey2-9` SET NULL / SET DEFAULT delete actions and `fkey2-11` CASCADE DELETE row-trigger ordering.

## Behavior

Extended `SQLiteRealUpstreamTriggerFkeyDynamicTest.php` with a generated delete-action matrix over generic setting/child rows. The new cases exercise distinct parent key ranges across:

- SET NULL child rewrites after parent DELETE.
- SET DEFAULT child rewrites to an existing default parent.
- CASCADE DELETE child removal.
- AFTER DELETE trigger audit visibility after FK actions.
- RETURNING old-row projection for deleted parent keys.

## Evidence

- Before: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicTest.php`
  - `1 test files, 603 assertions, 0 failures`
  - `46` PASS lines
- After: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicTest.php`
  - `1 test files, 1131 assertions, 0 failures`
  - `94` PASS lines

Focused delta:

- New PASS cases: `48`
- New focused assertions: `528`
- Expected `phpPass`: `207759 -> 207807`
- Mapped coverage remains `958 / 1589`; this is additional behavior-backed PHP corpus coverage and does not claim a new denominator row.

## Non-Overlap

This follow-up stays inside the real upstream trigger/FK dynamic domain but does not repeat the accepted row-trigger timing batch from `trigger2.test` or the existing fixed `fkey2-4`, `fkey2-9`, `fkey2-11`, `fkey2-12`, and `fkey2-20` single-case assertions. It adds distinct offset parent/child row ranges for delete-action behavior only.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PHP trigger/FK/RETURNING helper and adds focused corpus assertions.

## Root Harness

Not run; isolated micro-slice only.
