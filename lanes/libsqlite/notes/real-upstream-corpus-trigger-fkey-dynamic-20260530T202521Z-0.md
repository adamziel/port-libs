# real-upstream-corpus-trigger-fkey-dynamic-20260530T202521Z-0

Base accepted HEAD: `a5d711ea245dda1130ca2ff1ba1b791f9a863c2b`.

Implemented one real upstream trigger/FK behavior cluster from
`/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey6.test`:

- `fkey6-1.5.1` / `fkey6-1.5.2`: deferred FK dbstatus is raised while outstanding deferred FK violations exist.
- `fkey6-1.10.1`: `PRAGMA defer_foreign_keys` resets after COMMIT or ROLLBACK.
- `fkey6-3.2` / `fkey6-3.3.4`: `defer_foreign_keys` defers RESTRICT timing enough for an AFTER DELETE trigger to repair the parent row.
- `fkey6-4.1` / `fkey6-4.2`: an outstanding deferred FK violation still fails COMMIT and rolls back the statement effect.

Changed behavior:

- Added `SQLiteDynamicTriggerForeignKeyPlan::deferForeignKeysPragmaTransaction()` to model the fkey6 transaction boundary, deferred violation status, RESTRICT timing, repair triggers, and rollback/commit results using generic parent/child rows.
- Added `SQLiteRealUpstreamTriggerFkeyDynamicDeferPragmaCorpusTest.php` with 5,008 focused TestRunner PASS assertions.

Verification:

- `php -l lanes/libsqlite/src/SQLiteDynamicTriggerForeignKeyPlan.php` passed.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicDeferPragmaCorpusTest.php` passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicDeferPragmaCorpusTest.php` passed: `1 test files, 5008 assertions, 0 failures`.

Dependency closure:

- No new support component is needed. The slice reuses the existing bounded trigger/FK planning helper surface and the hydrated upstream SQLite `.test` checkout as source truth.

Non-overlap:

- This slice does not repeat the existing real upstream trigger/FK dynamic files covering fkey1 replace cascade, fkey2 composite/nocase behavior, trigger1/trigger3 RAISE behavior, triggerC recursion, or fkey3/fkey4 self-reference/autocommit cases. It owns `fkey6.test` defer-pragma transaction-boundary behavior only.
