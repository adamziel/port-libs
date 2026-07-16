# real-upstream-corpus-trigger-fkey-dynamic-20260530T182815Z-0

- Slice: `real-upstream-corpus-trigger-fkey-dynamic-20260530T182815Z-0`
- Base accepted HEAD: `2b09fd94bbc734a3a9855d41884522c7a5a06914`
- Upstream source truth:
  - `/home/claude/port-libs/.upstream-cache/libsqlite/test/trigger2.test`
  - Scenario ranges: `trigger2-3.1`, `trigger2-3.2`, `trigger2-4.1`, `trigger2-4.2`, `trigger2-5`, `trigger2-6.1a..6.1h`, and `trigger2-6.2a..6.2h`.

## Behavior

Extended `SQLiteRealUpstreamTriggerFkeyDynamicCorpusTest.php` with real
upstream `trigger2.test` behavior that was not covered by the earlier
`trigger2-1.1..1.3` row-trigger timing slice:

- `UPDATE OF c,d` triggers fire only when the statement touches named columns.
- `WHEN new.a > 20` and subquery `WHEN (SELECT count(*) FROM tbl) = 0`
  predicates fire against the expected new row and pre-insert table state.
- Trigger programs may cascade into triggers on other tables while direct
  recursive trigger programs remain bounded when recursive triggers are off.
- The count-changes boundary reports only the outer statement row, excluding
  trigger-program side effects.
- Outer `INSERT OR ...` and `UPDATE OR ...` conflict policies propagate into
  trigger-body conflicts, including `IGNORE`, `REPLACE`, and `ROLLBACK`.

The coverage is source-neutral and uses generic row data only.

## Evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicCorpusTest.php`
  - `1 test files, 16124 assertions, 0 failures`

Focused delta inside the existing real trigger/FK corpus:

- New distinct TestRunner PASS cases: `386`
- Previous focused assertion baseline for this file: `4676`
- New focused assertion total for this file: `16124`
- New focused assertions: `11448`
- `lane-status.json` local projection: `298721 -> 299107 pass / 0 fail`
- Mapped denominator coverage: unchanged.

## Non-Overlap

This does not repeat the accepted `fkey1`, `fkey2`, `fkey6`, `trigger1`,
`trigger2-1`, or `triggerC-5` dynamic trigger/FK corpus surfaces. It focuses
on the next upstream `trigger2.test` trigger-program, conditional-trigger,
count-changes, and conflict-policy sections.

## Dependency Closure

No new support component is needed. The slice reuses the existing lane-local
dynamic trigger/FK planner and adds focused tests around already implemented
native PHP trigger execution primitives.

## Next

Continue with a non-overlapping upstream range such as `trigger2.test` view
trigger behavior or `trigger3.test` `RAISE()` behavior, only if it can maintain
real-corpus assertion growth.
