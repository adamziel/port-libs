# real-upstream-corpus-upsert-returning-dynamic-20260530T181026Z-0

Upstream source file:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert5.test`
  - Ported the explicit six-schema matrix for generalized UPSERT scenarios `upsert5-1.1.100` through `upsert5-1.6.505`.
  - The matrix covers integer primary-key tables, explicit primary-key-index tables, WITHOUT ROWID tables, and reverse unique-declaration order variants.
  - Focus: conflict-arm order, duplicate conflict arms, catch-all arms, DO UPDATE, DO NOTHING, skipped rows, RETURNING row suppression, and declared column-order projection.

Focused count:

- Added `2281` focused TestRunner PASS cases and `2511` assertions in `SQLiteRealUpstreamUpsert5FullMatrixTest.php`.
- This satisfies the current real-corpus handoff floor via more than 1000 distinct focused PASS cases.

Non-overlap:

- This expands the earlier compressed dynamic UPSERT coverage with explicit upstream schema variants and exact upstream test ids.
- It does not add metadata-only rows or fabricated script ids, and it does not repeat the existing trigger lifecycle, row-value RETURNING, recursive view UPSERT, or generic target-analysis files.

Dependency closure:

- No new support component is needed. The test reuses the existing native PHP `SQLiteUpsertDoUpdateWherePlan::executeConflictArms()` and `returningRows()` helpers.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamUpsert5FullMatrixTest.php` passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamUpsert5FullMatrixTest.php` passed with `1 test files / 2511 assertions / 0 failures` and `2281` PASS lines.
- Root harness not run; isolated micro-slice only.
