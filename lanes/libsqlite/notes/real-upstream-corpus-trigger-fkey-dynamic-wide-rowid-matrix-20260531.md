# Real Upstream Trigger/FK Dynamic Wide Rowid Matrix

Micro-slice: `real-upstream-corpus-trigger-fkey-dynamic-20260531T032821Z-0`

Base accepted HEAD: `9f3a6190507c2ea8ee290883ee3ce143ab18c8c9`

Added focused PHP coverage in `SQLiteRealUpstreamTriggerFkeyDynamicWideRowidMatrixTest.php`.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/triggerB.test`, `triggerB-3.1..3.2`: wide `OLD`/`NEW` column masks beyond a 32-bit trigger mask.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/triggerD.test`, `triggerD-1.1..2.4`: rowid/oid/_rowid_ alias binding in trigger `OLD` and `NEW` rows.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/triggerF.test`, `triggerF-1.2..1.4`: WITHOUT ROWID delete triggers fired by replace/update conflict deletes.

Focused evidence:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicWideRowidMatrixTest.php`
  - `No syntax errors detected`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicWideRowidMatrixTest.php`
  - `1 test files, 1003 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - `1 test files, 3 assertions, 0 failures`
- `git diff --check -- lanes/libsqlite`
  - passed with no output

Non-overlap:

- Does not repeat the accepted trigger/FK attached restrict, fkey2 recursive cascade pragma, schema drop, trigger RAISE expression, triggerA view routing, triggerG recursive select, or rowid-variable corpus files.
- Uses existing generic `SQLiteDynamicTriggerForeignKeyPlan` behavior and adds no new WordPress-specific API or fixture surface.

Dependency closure:

- No new support component is needed. The batch reuses the existing bounded native PHP trigger/FK behavior helper.

Expected dashboard movement:

- Focused TestRunner growth: +1003 assertions/PASS cases if accepted as a selected lane test.
- Mapped denominator: unchanged.
