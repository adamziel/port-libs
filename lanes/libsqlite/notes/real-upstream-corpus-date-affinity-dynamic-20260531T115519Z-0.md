# real-upstream-corpus-date-affinity-dynamic-20260531T115519Z-0

Session: `port-dev-sqlite-yield-dyn-real-date-20260531T115519Z`
Base accepted HEAD: `ab384a0d481bd4acef6592a38a3540df9d0cc3f2`

## Upstream Source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/atof1.test`
- Scenario range: `atof-3.2`
- Owned window: generated decimal text values
  `format('18.44674407370955%04d', suffix)` for suffixes `0000..0999`.

## Behavior

- Added a real upstream corpus batch for decimal text to REAL conversion near
  the `18.44674407370955...` mantissa boundary.
- Each row compares native SELECT execution against a local `sqlite3` oracle
  for `typeof(CAST(... AS REAL))`, `format('%.10e', CAST(... AS REAL))`, and
  the upstream `GLOB '18.446744073709*'` guard.
- Each row also verifies native helper casting, REAL insert affinity storage,
  and SQLite source-truth quote/cast round-trip preservation.
- Fixed `SQLiteCoreScalarFunction::format()` scientific notation so `%e` and
  `%E` exponents are normalized to SQLite-style two-digit exponent fields,
  including alternate-form float formatting.

## Verified Movement

- New focused test file:
  `SQLiteRealUpstreamCorpusDateAffinityDynamicAtof1DecimalMantissa20260531T115519ZTest.php`
- Focused behavior assertions: `12013`
- Focused PASS cases in that file: `1002`
- Countable movement: real PHP TestRunner PASS/assertion growth over already
  mapped upstream inventory; mapped denominator remains `1589 / 1589`.

## Non-overlap

This owns only `atof1.test` `atof-3.2` suffixes `0000..0999`. It avoids the
accepted `atof-3.1` integer-prefix suffixes `0592..1609`, accepted `atof2`
rounding and alternate-form-2 coverage, `date4` row ranges, date/timediff
matrices, and affinity/type dynamic batches.

## Verification

- Red-first before source fix:
  `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusDateAffinityDynamicAtof1DecimalMantissa20260531T115519ZTest.php`
  failed on SQLite `%e` exponent padding parity: sqlite3 expected
  `1.8446744074e+01`, native formatting returned `1.8446744074e+1`.
- Focused after fix:
  `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusDateAffinityDynamicAtof1DecimalMantissa20260531T115519ZTest.php`
  passed with `1 test files, 12013 assertions, 0 failures`.
- Adjacent accepted atof family:
  `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusDateAffinityDynamicAtof1RealConversion20260531T102903ZTest.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusDateAffinityDynamicAtof2Rounding20260531T104656ZTest.php`
  passed with `2 test files, 17205 assertions, 0 failures`.
- Scalar formatter/select dispatch adjacency:
  `php tools/run-tests.php lanes/libsqlite/tests/SQLiteSelectSqlCoreScalarFunctionCorpusTest.php`
  passed with `1 test files, 72 assertions, 0 failures`.
- API guard:
  `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  passed with `1 test files, 3 assertions, 0 failures`.
- `php -l lanes/libsqlite/src/SQLiteCoreScalarFunction.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusDateAffinityDynamicAtof1DecimalMantissa20260531T115519ZTest.php`
- `git diff --check -- lanes/libsqlite`

## Dependency Closure

No new support component is needed. The slice reuses native
`SQLiteSelectSql` CAST/GLOB/function dispatch,
`SQLiteRealExpressionAffinityCorpusPlan` REAL affinity helpers,
`SQLiteCoreScalarFunction` quote/format support, and the local `sqlite3`
oracle used by adjacent upstream corpus tests.

## Follow-up

Exact `quote(CAST(... AS REAL))` text spelling differs from sqlite3 for this
mantissa range, but the upstream `atof-3.2` invariant is quote/cast
round-trip preservation. Exact REAL `quote()` text parity should be handled as
a separate formatter slice if needed.
