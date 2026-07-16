# real-upstream-corpus-upsert-returning-dynamic-20260531T121049Z-0

## Source Truth

- Upstream file: `/home/claude/port-libs/.upstream-cache/libsqlite/test/changes2.test`
- Ported scenarios:
  - `changes2-1.1` through `changes2-1.4`: a prepared `UPDATE ... RETURNING` statement exposes the statement change count while the first `SQLITE_ROW` is stepped and preserves it through `SQLITE_DONE`, including after reset/schema replacement.
  - `changes2-2.1` through `changes2-2.4`: a prepared `INSERT INTO log VALUES(changes() || ' changes')` evaluates `changes()` from the prior completed DML statement before the log insert records its own one-row change.

## Handoff Delta

- Added `SQLiteReturningPreparedStatementPlan` to model the prepared RETURNING step trace and prepared `changes()` log insert boundary using existing `SQLiteConnectionCounters`.
- Added `SQLiteRealUpstreamReturningChangesPreparedDynamicTest.php` with 1000 dynamic corpus cases plus 3 guard/source/dependency cases.
- Focused evidence delta: +1003 PASS cases, 23005 assertions, 0 failures.
- `lane-status.json` selected evidence moves from 2906760 to 2907763 pass / 0 fail; mapped coverage remains 1589 / 1589.

## Verification

- `php -l lanes/libsqlite/src/SQLiteReturningPreparedStatementPlan.php` passed.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamReturningChangesPreparedDynamicTest.php` passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamReturningChangesPreparedDynamicTest.php` passed: 1 test files, 23005 assertions, 0 failures.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php` passed: 1 test files, 3 assertions, 0 failures.
- `git diff --check -- lanes/libsqlite` passed.
- `php -r '$data = json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true); if (!is_array($data)) { fwrite(STDERR, json_last_error_msg() . PHP_EOL); exit(1); } echo "lane-status json ok\n";'` passed.

## Non-Overlap

This slice does not repeat accepted UPSERT arm priority, RETURNING virtual table, trigger, target-alias, dynamic SELECT input, or `returning1.test` dynamic result-shape batches. It ports the separate upstream `changes2.test` prepared-statement counter boundary and reuses existing counter support.

## Dependency Closure

No new support component is needed. The patch reuses the existing bounded `SQLiteConnectionCounters` component and adds only a focused prepared-statement behavior model under `lanes/libsqlite/src`.
