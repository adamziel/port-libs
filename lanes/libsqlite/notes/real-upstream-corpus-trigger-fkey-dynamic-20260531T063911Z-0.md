# real-upstream-corpus-trigger-fkey-dynamic-20260531T063911Z-0

Base accepted HEAD: `adb26e7f16ecd89937cf2d16ad3f15841131934b`.

## Upstream Source Truth

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_fkey.test`
- Ported section: `e_fkey-38.1..38.8`

## Behavior

- Failed `COMMIT` due to a deferred foreign-key violation preserves the nested
  savepoint stack and the violating row images.
- Failed release of an outer transaction savepoint behaves like `COMMIT`: it
  fails while leaving nested savepoints open.
- `ROLLBACK TO` a nested savepoint removes only the row images after that
  savepoint; a later rollback to the parent savepoint repairs the remaining
  deferred violation and allows release.

## Changed Lane Files

- `lanes/libsqlite/src/SQLiteUpstreamTriggerFkeyDynamicPlan.php`
- `lanes/libsqlite/tests/SQLiteUpstreamTriggerFkeyDynamicRealCorpusTest.php`
- `lanes/libsqlite/lane-status.json`
- `lanes/libsqlite/notes/real-upstream-corpus-trigger-fkey-dynamic-20260531T063911Z-0.md`

## Focused Evidence

- `php -l lanes/libsqlite/src/SQLiteUpstreamTriggerFkeyDynamicPlan.php`
  - `No syntax errors detected`
- `php -l lanes/libsqlite/tests/SQLiteUpstreamTriggerFkeyDynamicRealCorpusTest.php`
  - `No syntax errors detected`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteUpstreamTriggerFkeyDynamicRealCorpusTest.php`
  - `1 test files, 1666 assertions, 0 failures`
- `git diff --check -- lanes/libsqlite`
  - passed

## Countability

- Focused selected movement: `+168` real TestRunner PASS/assertion cases in
  the existing upstream trigger/FK dynamic corpus file.
- Mapped denominator coverage remains `1589 / 1589`; this is behavior growth,
  not new denominator mapping.

## Non-Overlap

This does not repeat accepted `fkey2-2.*`, `fkey7`, `fkey8`, `trigger2`,
`trigger9`, `triggerC`, `triggerG`, or the older lane-local `e_fkey-36/37`
transaction-savepoint coverage. The new surface is specifically
`e_fkey-38.1..38.8` failed deferred-FK COMMIT/RELEASE preservation of nested
savepoints and targeted rollback repair.

## Dependency Closure

No new support component is needed. The slice reuses the lane-local upstream
trigger/FK dynamic corpus helper and the hydrated SQLite upstream checkout as
source truth.
