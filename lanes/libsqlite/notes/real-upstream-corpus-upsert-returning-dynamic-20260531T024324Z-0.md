# real-upstream-corpus-upsert-returning-dynamic-20260531T024324Z-0

Source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert4.test`
  - `upsert4-6.1.*`: `INSERT OR REPLACE ... ON CONFLICT(b) DO NOTHING` processes the UPSERT conflict arm before any replacement side conflict.
  - `upsert4-6.2.*`: `INSERT OR REPLACE ... ON CONFLICT(b|c) DO UPDATE` updates the selected conflicting row before any replacement side conflict.

Implementation:

- Added `SQLiteRealUpstreamUpsertReturningReplacePrecedenceDynamicTest.php`.
- The file uses the existing native `SQLiteUpsertDoUpdateWherePlan::executeConflictArms()` path with generic `app_settings`-style rows and unique constraints on `setting_id`, `key_name`, and `slot`.
- No production source change was needed; this exercises already-present native conflict-arm ordering, `DO NOTHING`, `DO UPDATE`, change-count, and `RETURNING` row-image behavior.

Focused count:

- 2,001 focused TestRunner PASS cases.
- 250 dynamic seeds, each asserting key-conflict `DO NOTHING`, slot-conflict `DO NOTHING`, key-conflict `DO UPDATE`, slot-conflict `DO UPDATE`, selected conflict-arm metadata, change counts, final row images, and `RETURNING` rows.

Non-overlap:

- This does not repeat the prior `upsert4-1.*` conflict/update/abort/move slice, `upsert4-7.*` excluded/current alias matrix, `upsert4-8.*` table-named `excluded` behavior, accepted `upsert5` arm-priority matrices, `upsert2` SELECT input cases, or trigger/FK/row-value RETURNING helpers.
- This slice owns the `upsert4.test` `INSERT OR REPLACE` precedence cluster through generic UPSERT/RETURNING row-stream assertions.

Dependency closure:

- No new support component is needed. The slice reuses the native PHP conflict-arm UPSERT executor and unique-constraint checks.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningReplacePrecedenceDynamicTest.php` passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningReplacePrecedenceDynamicTest.php` passed: `1 test files, 3751 assertions, 0 failures`.
