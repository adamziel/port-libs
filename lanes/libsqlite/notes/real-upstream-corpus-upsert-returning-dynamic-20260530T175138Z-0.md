# real-upstream-corpus-upsert-returning-dynamic-20260530T175138Z-0

Upstream source file:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/returning1.test`
  - Ported `returning1-11.1` through `returning1-11.7` TEMP trigger side-effect ordering around `RETURNING` rows.
  - Ported `returning1-17.1` and `returning1-17.2` repeated `ON CONFLICT DO UPDATE RETURNING fooid` behavior for ordinary and TEMP tables.
  - Ported `returning1-20.1` through `returning1-20.3` correlated `RETURNING` subqueries that must be recomputed after each deleted row.

Focused assertion count:

- Added `281` focused PHP TestRunner PASS cases with `2081` assertions in `SQLiteRealUpstreamUpsertReturningDynamicCorrelatedTest.php`.
- Initial focused run was red because the repeated `fooval` UPSERT expected a duplicate final row. The expectation was corrected to SQLite's upstream behavior: the third input updates the existing `fooval=17` row and returns its existing `fooid`.

Non-overlap:

- This follows the earlier dynamic UPSERT arms and `upsert5`/`returning1-4` coverage without repeating those sections.
- It avoids row-value RETURNING windows, trigger recursive view RETURNING, upsert4 target analysis, accepted `upsert5` arm-order coverage, and source-neutral cleanup work.

Dependency closure:

- No new support component is needed. The slice reuses existing native PHP row-array UPSERT/RETURNING projection helpers and adds focused corpus assertions only.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningDynamicCorrelatedTest.php` passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningDynamicCorrelatedTest.php` passed with `1 test files / 2081 assertions / 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php` passed with `1 test files / 3 assertions / 0 failures`.
- `php -r 'json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'` passed.
- `git diff --check -- lanes/libsqlite` passed.
- Root harness not run; isolated micro-slice only.
