# real-upstream-corpus-trigger-fkey-dynamic-20260531T152402Z-0

Accepted base: `92a6f092c9582e866c5b2412b97dd190e3f378da`

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_fkey.test`
- Ported sections: `e_fkey-18.1..18.9`, `e_fkey-19.1..19.5`, `e_fkey-20.1..20.8.6`, `e_fkey-21.1..21.8`, `e_fkey-22.OFF.1..22.ON.4`, `e_fkey-23.1..23.7`, `e_fkey-24.1..24.4.3`

Patch summary:

- Added `SQLiteForeignKeySchemaRequirementPlan` to model upstream foreign-key schema requirements for required parent primary/unique keys, parent unique-index collation matching, DML-time schema mismatch diagnostics, child definition column-count errors, implicit parent primary-key mapping, and optional child-key index behavior.
- Added `SQLiteRealUpstreamTriggerFkeyDynamicRequiredIndex20260531Test.php`, which cites the hydrated upstream Tcl file and verifies five dynamic variants of the ported corpus.
- Updated `lane-status.json` from `2936779` to `2938106` selected PASS lines (`+1327`), with focused assertion evidence at `1339` assertions.

Focused evidence:

- `php -l lanes/libsqlite/src/SQLiteForeignKeySchemaRequirementPlan.php` => no syntax errors
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicRequiredIndex20260531Test.php` => no syntax errors
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicRequiredIndex20260531Test.php` => `1 test files, 1339 assertions, 0 failures`; `1327` PASS lines
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php` => `1 test files, 3 assertions, 0 failures`
- `git diff --check -- lanes/libsqlite` => clean

Non-overlap:

- This slice does not repeat accepted trigger1 raise/datatype mismatch, fkey malloc/update/delete coverage, recursive trigger, UPSERT/RETURNING trigger, or previously accepted app-WAL/PRAGMA/B-tree/SELECT corpus slices.
- The owned surface is the upstream `e_fkey.test` required/suggested schema-index corpus in sections 18 through 24.

Dependency closure:

- No new support component is needed. The slice reuses the existing PHP TestRunner, hydrated upstream corpus checkout, and generic libsqlite source namespace.

Follow-up:

- Additional trigger/FK dynamic slices should skip `e_fkey-18.1..24.4.3` and target a distinct upstream trigger/fkey section or a named runner/root blocker.
