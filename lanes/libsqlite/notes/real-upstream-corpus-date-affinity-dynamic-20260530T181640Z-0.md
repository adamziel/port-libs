# Real Upstream Date/Affinity Dynamic Corpus

Session: `port-dev-sqlite-yield-dyn-real-date-20260530T181640Z`
Base accepted HEAD: `a9928e604a7d849ecf8aa28f83049e71a24f4b05`

## Upstream Source Truth

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/date.test`
  - `date-2.2c-0` through `date-2.2c-999`: fractional unixepoch millisecond `strftime('%H:%M:%f', ..., 'unixepoch')` behavior.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/affinity2.test`
  - `affinity2-110` through `affinity2-150`: inserted INTEGER, REAL, BLOB, NUMERIC, and TEXT affinity storage classes.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/affinity3.test`
  - `affinity3-110` and `affinity3-130`: REAL affinity preserved through join/view-style division behavior with automatic-index state varied upstream.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/types3.test`
  - `types3-1.4` and `types3-3.4`: double values bind as REAL and text-affinity comparisons keep textual representation.

## Handoff Delta

- Added `lanes/libsqlite/tests/SQLiteRealUpstreamDateAffinityDynamicNextCorpusTest.php`.
- Focused PASS-line growth: `+1010` selected PASS lines.
- Focused assertion growth: `+7023` assertions.
- Mapped denominator movement: none; this is PHP behavior coverage over hydrated upstream files, not runner-map admission.
- Non-overlap: this avoids existing `date2.test` deterministic schema guard/dynamic tests and does not repeat the recently accepted date2/date4/expr/window batches.

## Verification

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamDateAffinityDynamicCorpusTest.php`
  - `1 test files, 7023 assertions, 0 failures`
- Dependency closure: no new support component needed. The slice reuses existing `SQLiteCoreScalarFunction` date/time and type helpers plus `SQLiteSelectSql` row-array SELECT execution.

## Next

The next larger date/affinity batch should use different upstream sections, such as `date.test` calendar/timezone/null/modifier groups outside `date-2.2c`, or deeper `affinity2.test` comparison/index rows that require new executor/planner behavior.
