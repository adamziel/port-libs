# real-upstream-corpus-date-affinity-dynamic-20260531T074154Z-0

- Upstream source: `/home/claude/port-libs/.upstream-cache/libsqlite/test/date4.test`.
- Ported scenario: `date4.test` strftime libc parity loop, rows `date4-21400` through `date4-22399`, using `TS = i * 86390` and format `%d,%e,%F,%H,%k,%I,%l,%j,%m,%M,%u,%w,%W,%Y,%%,%P,%p,%U,%V,%G,%g`.
- Focused PHP test file: `lanes/libsqlite/tests/SQLiteRealUpstreamCorpusDateAffinityDynamicDate4Rows21400To22399Test.php`.
- Expected focused growth: `1004` TestRunner PASS cases and `7015` assertions from real upstream date4 behavior.
- Non-overlap: owns rows `21400..22399`, after accepted date4 rows `0..21399`, and avoids existing date/date2/date3/date5 modifier coverage plus affinity comparison/type-matrix shards.
- Dependency closure: no new support component needed; the batch reuses existing `SQLiteCoreScalarFunction` `strftime`/`unixepoch` behavior.
- Root harness: not run - isolated micro-slice.
