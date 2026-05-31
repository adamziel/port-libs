# real-upstream-corpus-select-core-dynamic-20260531T042539Z-0

## Scope

Added `SQLiteRealUpstreamCorpusSelectCoreDynamicBatch2Test.php` as a new real
upstream SELECT corpus batch. The batch cites and exercises hydrated upstream
SQLite sources:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/select6.test`
  - `select6-3.10`: derived grouped AVG subquery with outer WHERE and ORDER BY.
  - `select6-3.12`: derived grouped AVG subquery with HAVING and outer WHERE.
  - `select6-3.14`: grouped COUNT derived table ordered by aggregate alias.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/select5.test`
  - `select5-8.*`: grouped join COUNT behavior.
  - `select5-6.*`: NULL values group together for GROUP BY.

## Yield

- Focused PHP PASS cases: 1251.
- Focused assertions: 6254.
- Expected selected PASS-line movement: +1251 if admitted without overlap.
- Mapped denominator movement: none; the manifest already reports complete
  mapped coverage.

## Non-Overlap

This batch avoids the recently parked SELECT guard regressions and accepted
SELECT surfaces by not touching source and by avoiding compound-collation,
`select5` tail behavior, `e_select2` join-collation/select5 behavior,
`select8` LIMIT/OFFSET, expression ORDER BY, comma LIMIT, grouped SELECT SQL
text, subquery executor admission, and JSON table SELECT source wiring. The new
coverage is limited to dynamic derived aggregate subqueries, aggregate HAVING,
aggregate alias ordering, grouped join count, and NULL grouping behavior.

## Verification

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusSelectCoreDynamicBatch2Test.php`
  - `1 test files, 6254 assertions, 0 failures`

## Dependency Closure

No new support component is needed. The batch reuses the existing native
`SQLiteSelectSql` SELECT executor and in-memory row-array test harness.
