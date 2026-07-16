# real-upstream-corpus-upsert-returning-dynamic-20260530T212444Z-0

Ported a real upstream UPSERT trigger-firing cluster from
`/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert2.test`.

Upstream scenarios:

- `upsert2-300`: rowid table `ON CONFLICT DO UPDATE` fires
  `BEFORE INSERT`, `BEFORE UPDATE`, then `AFTER UPDATE`.
- `upsert2-310`: rowid table `ON CONFLICT DO NOTHING` fires only
  `BEFORE INSERT` and emits no changed row.
- `upsert2-320`: rowid table `DO UPDATE ... WHERE` false fires only
  `BEFORE INSERT`, leaves the row unchanged, and emits no changed row.
- `upsert2-400`, `upsert2-410`, `upsert2-420`: the same behavior on a
  `WITHOUT ROWID` table.

Implementation notes:

- No production change was needed. The slice reuses
  `SQLiteUpsertDoUpdateWherePlan::executeWithTriggerTrace()` and adds a
  focused real-corpus dynamic extension to the existing trigger dynamic test
  file.
- The dynamic variants vary the incoming values while preserving the cited
  upstream statement shapes and trigger-order expectations.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningTriggerDynamicTest.php`:
  no syntax errors.
- Red-first focused run before fixing the DO NOTHING wrapper shape:
  `1 test files / 1800 assertions / 600 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningTriggerDynamicTest.php`:
  `1 test files / 6561 assertions / 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningDynamicTest.php lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningSqlDynamicTest.php lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningTriggerDynamicTest.php`:
  `3 test files / 9869 assertions / 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`:
  `1 test files / 3 assertions / 0 failures`.
- `git diff --check -- lanes/libsqlite`: passed.

Non-overlap:

- This does not repeat the existing `upsert1`, `upsert2` repeated-conflict
  row-image, `upsert3`, `upsert4`, `upsert5`, `returning1-4`, `returning1-17`,
  or `returning1-20` coverage. It targets the trigger firing order for real
  upstream `upsert2` sections 300/310/320 and 400/410/420.

Dependency closure:

- No new support component is needed. Existing native PHP UPSERT conflict
  handling and trigger-trace modeling cover this bounded upstream cluster.
