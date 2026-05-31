# Real Upstream Trigger/FK Dynamic Recursive Once

Base accepted HEAD: `8c83cd38b21e6ef37afec24c7a1c1aa06c561658`.

Owned upstream sections:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/triggerF.test`
  `triggerF-1.1.0..1.4.2`: WITHOUT ROWID table DELETE, `INSERT OR REPLACE`,
  and `UPDATE OR REPLACE` delete-trigger log timing.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/triggerG.test`
  `triggerG-100..200`: recursive trigger SELECT/index-loop behavior where
  OP_Once state must reset per recursive trigger frame.

Focused behavior added:

- `SQLiteDynamicTriggerForeignKeyPlan::triggerFWithoutRowidDeleteReplacePlan()`
  models BEFORE/AFTER DELETE trigger visibility around conflicting replace
  deletes on a WITHOUT ROWID primary key.
- `SQLiteDynamicTriggerForeignKeyPlan::triggerGRecursiveSelectOncePlan()`
  models recursive trigger row production and indexed `IN` loop results for
  single-source and join SELECT programs.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicRecursiveOnceTest.php`
  passed with `1 test files, 10991 assertions, 0 failures`.

Expected selected movement if accepted: `+10991` focused PASS/assertion cases,
from `1262570` to `1273561` pass / `0` fail. Mapped denominator coverage remains
`1589 / 1589`.

Non-overlap: this slice does not repeat the accepted triggerD rowid alias,
triggerE stored variable, trigger5 undo, trigger2/FK yield, fkey1, fkey5,
fkey6, fkey7, or fkey8 dynamic trigger/FK sections.

Dependency closure: no new support component is needed; the slice reuses the
existing native PHP trigger/FK planning surface and the hydrated upstream
SQLite test corpus as source truth.
