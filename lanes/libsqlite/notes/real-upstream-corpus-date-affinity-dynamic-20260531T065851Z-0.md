# real-upstream-corpus-date-affinity-dynamic-20260531T065851Z-0

- Base accepted HEAD: `b596d6a43afd4ccaf50904f879de33fed9b5b7f3`.
- Upstream source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/date.test`.
- Owned upstream section: `date.test` section 16, `date-16.1` through `date-16.31`, extreme date/time boundary behavior around SQLite's supported Julian-day range.
- Added focused PHP test: `lanes/libsqlite/tests/SQLiteRealUpstreamCorpusDateAffinityDynamicExtremeRange20260531T065851ZTest.php`.
- Focused scope: exact upstream section-16 boundary rows plus generated near-limit seconds/minutes/hours/days/months/years rows using `sqlite3` as an oracle, with native `SQLiteCoreScalarFunction` value and storage-class checks.
- Focused movement: `1268` focused TestRunner PASS cases, `3804` assertions, `0` failures.
- Non-overlap: avoids accepted `date4` loop rows, `date10` time-only defaults, `date11` HH:MM modifiers, `date13` fractional word modifiers, `date19` floor/ceiling, `date20` no-round, date3 strftime rows, and expression-affinity batches.
- Dependency closure: no new support component needed; the slice reuses native `SQLiteCoreScalarFunction` date/time range checks and local `sqlite3` oracle evidence.
- Root harness: not run - isolated micro-slice.
