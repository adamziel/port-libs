# real-upstream-corpus-upsert-returning-dynamic-20260530T170825Z-0

Upstream source files:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert5.test`
  - Ported generalized multi-arm UPSERT ordering scenarios `upsert5-1.$tn.100` through `upsert5-1.$tn.505`.
  - Focus: dynamic ON CONFLICT arm selection, duplicate conflict arms, catch-all arms, and DO NOTHING fall-through.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/returning1.test`
  - Ported mixed INSERT/UPDATE `RETURNING` behavior from `returning1-4.2` and `returning1-4.5`.
  - Focus: changed-row order, star projection, literal separator projection, and final row state after mixed insert/update UPSERT.

Focused assertion count:

- Added `198` focused PHP TestRunner assertions in `SQLiteRealUpstreamCorpusUpsertReturningDynamicTest.php`.

Non-overlap:

- This batch does not repeat accepted row-value conflict, recursive view UPSERT, trigger/FK RETURNING, or UPDATE/DELETE RETURNING slices.
- It uses the existing generic `SQLiteUpsertDoUpdateWherePlan::executeConflictArms()` and `returningRows()` helpers against real upstream dynamic multi-arm UPSERT and mixed RETURNING cases.

Dependency closure:

- No new support component is needed. The slice reuses existing native PHP UPSERT conflict-arm and RETURNING projection helpers.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusUpsertReturningDynamicTest.php` passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusUpsertReturningDynamicTest.php` passed with `1 test files / 198 assertions / 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php` passed with `1 test files / 3 assertions / 0 failures`.
- `php -r 'json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'` passed.
- `git diff --check -- lanes/libsqlite` passed.
- Root harness not run; isolated micro-slice only.
