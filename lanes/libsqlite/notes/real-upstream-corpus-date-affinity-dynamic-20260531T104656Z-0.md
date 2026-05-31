# real-upstream-corpus-date-affinity-dynamic-20260531T104656Z-0

Implemented an additive real upstream `atof2.test` rounding corpus slice.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/atof2.test`
- Scenario names: `atof2-1.0`, `atof2-1.1`, `atof2-2.1`, and `atof2-2.2`.

Behavior covered:

- `format('%g', ...)` rounding across 1000 generated decimal inputs around the upstream `192.496475` / `192.496501` rounding boundary.
- SQLite `printf()`/`format()` alternate-form-2 `!` flag parsing for floating-point conversions.
- `format('%!.30f', ieee754_inc(100.0,-1))` and `format('%!.30f', ieee754_inc(100.0,-2))` exact output parity using the equivalent PHP double inputs and sqlite3 oracle text.
- SELECT scalar-function dispatch and TEXT affinity preservation for the formatted output.

Focused evidence:

- Red precheck before the source edit: `SQLiteCoreScalarFunction::sqlFunctionArguments('format', ['%!.30f', 99.99999999999999])` returned the literal `%!.30f`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusDateAffinityDynamicAtof2Rounding20260531T104656ZTest.php` -> `1 test files, 8034 assertions, 0 failures`.
- Guard/adjacent verification: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusDateAffinityDynamicAtof2Rounding20260531T104656ZTest.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusDateAffinityDynamicAtof1RealConversion20260531T102903ZTest.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php` -> `3 test files, 17208 assertions, 0 failures`.
- PHP lint passed for changed PHP files, and `git diff --check -- lanes/libsqlite` passed.

PASS/assertion delta:

- Adds 1005 focused TestRunner PASS cases.
- Adds 8034 focused behavior assertions.

Non-overlap:

- This owns `atof2.test` rounding and alternate-form-2 REAL formatting.
- It does not repeat accepted `atof1.test` suffix conversion, `date4.test` row ranges, `date5.test` calendar cycles, `timediff6`, or `types.test`/`types3.test` storage-class affinity batches.

Dependency closure:

- No new support component is needed. The patch reuses native `SQLiteCoreScalarFunction`, `SQLiteSelectSql` scalar function dispatch, TEXT affinity storage helpers, and the existing local `sqlite3` oracle path used by adjacent upstream corpus tests.
