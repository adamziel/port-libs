# Real Upstream Trigger/FK Dynamic Rowid Variable Corpus

Slice: `real-upstream-corpus-trigger-fkey-dynamic-20260530T220757Z-0`

Base accepted HEAD: `982e8dd8663ac2abd3a38d17e45a83e32b2f3371`

Upstream source files:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/triggerD.test`
  - `triggerD-1.1..1.4`: declared `rowid`, `oid`, and `_rowid_` columns shadow physical rowid aliases in trigger `OLD`/`NEW` references.
  - `triggerD-2.1..2.4`: tables without declared rowid-name columns use physical rowid aliases, including `-1` in the before-insert trigger.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/triggerE.test`
  - `triggerE-1.1.*` / `triggerE-1.2.*`: trigger definitions with bound variables are rejected at create time.
  - `triggerE-2.1..2.3`: trigger definitions loaded from schema text coerce variable references to SQL NULL at runtime.

Changed behavior:

- Added `SQLiteDynamicTriggerForeignKeyPlan::triggerRowidAliasResolutionPlan()` for triggerD rowid alias resolution across insert/update/delete and declared versus physical rowid columns.
- Added `SQLiteDynamicTriggerForeignKeyPlan::triggerVariableReferencePlan()` for triggerE create-time variable rejection and writable-schema loaded NULL coercion.
- Added `SQLiteRealUpstreamTriggerFkeyDynamicRowidVariableCorpusTest.php` with 4,089 focused assertions / PASS lines.

Verification:

- `php -l lanes/libsqlite/src/SQLiteDynamicTriggerForeignKeyPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicRowidVariableCorpusTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicRowidVariableCorpusTest.php`
  - `1 test files, 4089 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - `1 test files, 3 assertions, 0 failures`
- `git diff --check -- lanes/libsqlite`

Expected dashboard movement:

- `phpPass`: `911920 -> 916009` if accepted.
- `benchmarkDenominator.mapped`: unchanged at `1589 / 1589`; this is behavior PASS-line growth against already mapped real upstream trigger corpus.

Dependency closure:

- No new support component is needed. The slice reuses existing in-lane dynamic trigger/FK planning helpers and the hydrated upstream SQLite checkout as source truth.

Non-overlap:

- This does not repeat accepted trigger/FK fkey7, fkey8 replace, trigger2, trigger4 view, trigger5 undo, trigger7, trigger8 large-body, triggerG recursive, RAISE, or statement-preservation batches. It specifically covers triggerD rowid alias resolution and triggerE variable-boundary behavior.
