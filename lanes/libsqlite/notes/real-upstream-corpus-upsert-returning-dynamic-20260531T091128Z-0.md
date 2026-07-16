# Real Upstream Corpus: UPSERT RETURNING SQL Multi-Arm Dynamic

Base accepted HEAD: `c4826bfb8a7874ec9af5044a69ea78310604752e`

Micro-slice: `real-upstream-corpus-upsert-returning-dynamic-20260531T091128Z-0`

## Upstream Source Truth

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert5.test`
  - `1.$tn.100`: first matching generalized UPSERT arm wins when every unique constraint conflicts.
  - `1.$tn.201`: an earlier targeted arm preempts later matching arms.
  - `1.$tn.400`: final catch-all arm handles a conflict after targeted misses.
  - `1.$tn.422`: targeted `DO NOTHING` suppresses later catch-all update/RETURNING output.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert2.test`
  - `320/321`: matching `DO UPDATE ... WHERE false` suppresses RETURNING rows and does not fall through.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/returning1.test`
  - `4.5`: UPSERT RETURNING emits changed row images only.

## Patch

- `SQLiteUpsertReturningSql` now parses chained `ON CONFLICT` arms from INSERT SQL text and routes them through `SQLiteUpsertDoUpdateWherePlan::executeConflictArms()`.
- The compatibility parse return fields still reflect the first arm for existing single-arm tests and callers.
- Added `SQLiteRealUpstreamCorpusUpsertReturningSqlMultiArmDynamicTest.php` with 5 upstream-backed scenario families x 200 dynamic seeds, plus source/dependency proof tests.

## Evidence

- Red-first before the parser change:
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusUpsertReturningSqlMultiArmDynamicTest.php`
  - Result: `1 test files, 3 assertions, 1000 failures`
  - Representative failure: later `ON CONFLICT` text was parsed as part of the first `DO UPDATE SET` assignment list.
- Focused after the parser change:
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusUpsertReturningSqlMultiArmDynamicTest.php`
  - Result: `1 test files, 5003 assertions, 0 failures`
- Related regression coverage:
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteUpsertReturningSqlTest.php lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningSqlDynamicTest.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusUpsertReturningDynamicExcludedAliasSqlTest.php lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningSelectSourceDynamicTest.php lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningNoTargetDynamicTest.php lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningDoNothingOmittedTargetDynamicTest.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusUpsertReturningSqlMultiArmDynamicTest.php`
  - Result: `7 test files, 28745 assertions, 0 failures`
- Guard:
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - Result: `1 test files, 3 assertions, 0 failures`
- Syntax/diff:
  - `php -l lanes/libsqlite/src/SQLiteUpsertReturningSql.php`: no syntax errors.
  - `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusUpsertReturningSqlMultiArmDynamicTest.php`: no syntax errors.
  - `git diff --check -- lanes/libsqlite`: clean.

## Counts

- New focused PASS cases: `+1002`
- New focused behavior assertions: `5003`
- Expected `phpPass`: `2835919 -> 2836921`
- Mapped coverage remains `1589 / 1589`; this is PASS-line corpus growth over already mapped upstream files.

## Dependency Closure

No new support component is needed. This reuses the existing SQL parser surface in `SQLiteUpsertReturningSql` and the native `SQLiteUpsertDoUpdateWherePlan` conflict-arm executor.

## Non-Overlap

Existing accepted coverage already exercised native conflict-arm helper behavior. This slice adds SQL-text parsing and execution for chained generalized UPSERT arms with RETURNING, which was the missing parser-to-executor path.
