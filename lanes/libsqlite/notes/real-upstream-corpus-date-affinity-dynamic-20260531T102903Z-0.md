# real-upstream-corpus-date-affinity-dynamic-20260531T102903Z-0

Base accepted HEAD: `1681be96b403cae039655fef5cb4703982266b2d`

## Upstream Source

- Hydrated upstream file: `/home/claude/port-libs/.upstream-cache/libsqlite/test/atof1.test`
- Ported scenario: `atof-3.1`
- Owned range: suffixes `0592..1609` for `format('1844674407370955%04d', suffix)`
- Behavior: text-to-REAL conversion, REAL storage class projection, upstream GLOB guard, and `quote()` / `CAST(... AS REAL)` round-trip preservation.

## Non-Overlap

The obvious date rows in this slice family are already saturated by accepted/current tests, including broad `date4.test` ranges and focused `date.test`, `date2.test`, `date3.test`, `date5.test`, `timediff1.test`, `affinity3.test`, and `types3.test` batches. This handoff stays in the date/affinity bucket by using the remaining upstream numeric-affinity conversion corpus from `atof1.test`, and it owns only `atof-3.1` suffixes `0592..1609`.

## Focused Evidence

- New test file: `lanes/libsqlite/tests/SQLiteRealUpstreamCorpusDateAffinityDynamicAtof1RealConversion20260531T102903ZTest.php`
- New TestRunner PASS cases: `1020`
- Focused assertions: `9171`
- Focused command: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusDateAffinityDynamicAtof1RealConversion20260531T102903ZTest.php`
- Result: `1 test files, 9171 assertions, 0 failures`

## Dependency Closure

No new support component is needed. The test reuses `SQLiteSelectSql` CAST/GLOB/function dispatch, `SQLiteRealExpressionAffinityCorpusPlan` CAST helpers, `SQLiteCoreScalarFunction` `format()` / `quote()`, and the local `sqlite3` binary as oracle for hydrated upstream `atof1.test`.

## Next

If more date-affinity corpus work is needed, use a different `atof1.test` section such as `atof-3.2` or `atof-3.3`; do not extend this exact `atof-3.1` suffix window.
