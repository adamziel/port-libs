# real-upstream-corpus-upsert-returning-dynamic-20260531T074425Z-0

Base accepted HEAD: `9c30c680e4b44fbeb2fc11612b28622bb7d8e322`.

Added `SQLiteRealUpstreamCorpusUpsertReturningDynamicRowValueAssignmentTest.php`.

Upstream source sections:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert2.test` `upsert2-200`: repeated input rows and current row image behavior.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert5.test`: generalized `ON CONFLICT` target ordering and catch-all dispatch family.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/returning1.test` `returning1-4.5`: UPSERT `RETURNING` emits inserted and updated rows in statement order.

Focused behavior:

- Composite `(tenant_id,key_name)` conflict target.
- Row-value `DO UPDATE SET (value_text,hits,stamp)=(SELECT ...)`.
- `excluded` and current target-table expressions, `coalesce()`, concatenation, and addition.
- `WHERE app_settings.hits < excluded.hits AND excluded.load_policy GLOB 'e*'`.
- Dynamic inserted, updated, and skipped row classification with `RETURNING` order checked against a PDO SQLite oracle.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusUpsertReturningDynamicRowValueAssignmentTest.php` -> no syntax errors.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusUpsertReturningDynamicRowValueAssignmentTest.php` -> `1 test files, 1501 assertions, 0 failures`; 1001 focused PASS lines.

Expected dashboard movement if accepted:

- `phpPass`: `2717884 -> 2718885` (`+1001` focused PASS lines).
- `benchmarkDenominator.mapped`: unchanged at `1589 / 1589`.

Dependency closure:

- No new support component needed. The slice reuses existing `SQLiteUpsertReturningSql`, `SQLiteUpsertDoUpdateWherePlan`, `SQLiteDatabase::globMatches()`, and the local PDO SQLite oracle used by existing focused corpus tests.
