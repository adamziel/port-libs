# real-upstream-corpus-trigger-fkey-dynamic drop trigger

Accepted base: `ee0f86482fec002ad61b846f39a1a36b0fe0ecc4`.

Upstream source: `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_droptrigger.test`.

Owned upstream sections:

- `e_droptrigger-1.1`: qualified and unqualified `DROP TRIGGER` schema resolution.
- `e_droptrigger-2.*`: dropped INSERT triggers no longer fire.
- `e_droptrigger-3.1.*`: dropped UPDATE triggers no longer fire.
- `e_droptrigger-3.2.*`: dropped DELETE triggers no longer fire.
- `e_droptrigger-4.*`: trigger schema rows are removed when their owning trigger is gone.

Implementation:

- Added `SQLiteDynamicTriggerForeignKeyPlan::dropTriggerSchemaResolutionPlan()` for generic temp/main/attached trigger schema lookup, `IF EXISTS`, remaining schema rows, and event firing lists after a drop.
- Added `SQLiteRealUpstreamCorpusTriggerFkeyDynamicDropTriggerTest.php` with 250 dynamic DROP TRIGGER cases across INSERT/UPDATE/DELETE events and temp/main/attached schemas.

Focused evidence:

- `php -l lanes/libsqlite/src/SQLiteDynamicTriggerForeignKeyPlan.php`: no syntax errors.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusTriggerFkeyDynamicDropTriggerTest.php`: no syntax errors.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusTriggerFkeyDynamicDropTriggerTest.php`: `1 test files, 4209 assertions, 0 failures`, 4005 PASS lines.

Expected selected throughput movement:

- `phpPass`: `1040058 -> 1044063` after clean integration of this focused file.
- Mapped denominator remains `1589 / 1589`; this is behavior-producing real upstream corpus PASS-line growth, not new mapped denominator growth.

Dependency closure:

- No new support component is needed. The slice reuses the existing native PHP trigger/FK planning surface and the hydrated upstream SQLite Tcl checkout as source truth.

Non-overlap:

- This does not repeat accepted trigger/FK check, triggerA view/WHERE, trigger2 program, trigger5 undo, trigger7 name/pruning/drop catalog batch, triggerG recursive SELECT, or foreign-key action-matrix behavior. It targets `e_droptrigger.test` schema resolution and post-drop firing suppression.
