# real-upstream-corpus-date-affinity-dynamic-20260531T010600Z-0

Added `SQLiteRealUpstreamCorpusDateAffinityDynamicDate4Rows3300To4299Test.php` as an additive real upstream date/affinity corpus batch.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/date4.test`
- Scenario range: `date4-03300` through `date4-04299` from the real upstream `for {set i 0} {$i<=24858} {incr i}` `strftime($::FMT,$::TS,'unixepoch')` loop.

Focused assertion movement:

- 1000 distinct upstream row cases.
- 6014 focused assertions in the new file.
- Expected focused TestRunner PASS-line movement: +1003 PASS lines.

Non-overlap:

- This owns the next date4 continuation range, rows `3300..4299`.
- It avoids accepted date4 rows `300..3299`, `date5.test` Julian calendar roundtrips, `date3.test` unixepoch/auto/modifier placement coverage, `date2.test` deterministic schema guard coverage, and expression-affinity `types2` coverage.
- It does not add metadata-only denominator rows or fabricated script ids.

Dependency closure:

- No new support component is needed. The slice reuses native `SQLiteCoreScalarFunction` `strftime`, unixepoch modifier handling, scalar `typeof`, and UTC calendar formatting.
