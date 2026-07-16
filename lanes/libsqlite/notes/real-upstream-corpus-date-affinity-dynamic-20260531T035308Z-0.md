# Real Upstream Corpus Date Affinity Dynamic 20260531T035308Z-0

## Scope

- Added focused PHP coverage for real upstream SQLite `test/date.test` localtime/UTC chain behavior:
  - `date-6.28` through `date-6.32`
  - `date-6.20` localtime failure propagation
- The behavior exercises `SQLiteCoreScalarFunction::sqlFunctionArgumentsWithLocaltimeRules()` with generic application scheduling rows. No new source component was required.

## Evidence

- Focused test:
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusDateAffinityDynamicLocaltimeChain20260531T035308ZTest.php`
  - Result: `1 test files, 2529 assertions, 0 failures`
  - PASS lines: `508`
- Non-overlap:
  - Avoids accepted timezone suffix normalization, date component validation, date4 row sweeps, date5 exact-cycle coverage, Unixepoch/auto placement coverage, and broad expression-affinity dynamic tests.
  - This slice covers localtime/UTC modifier chaining and failure propagation from the upstream `date.test` test-control localtime section.

## Dependency Closure

- No new support component is needed. The existing bounded localtime-rule hook in `SQLiteCoreScalarFunction` is reused to model SQLite's upstream `SQLITE_TESTCTRL_LOCALTIME_FAULT` behavior.

## Follow-up

- Broader date completion can still target remaining known-red date cast-affinity and release/all-runner parity clusters, but this slice does not change production source or mapped denominator coverage.
