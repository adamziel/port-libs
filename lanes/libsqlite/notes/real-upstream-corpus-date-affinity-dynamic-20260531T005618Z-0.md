# real-upstream-corpus-date-affinity-dynamic-20260531T005618Z-0

- Upstream source: `/home/claude/port-libs/.upstream-cache/libsqlite/test/date4.test`.
- Ported behavior: `date4.test` loop rows `2300..3299`, where `TS = i * 86390` and `strftime($::FMT,$::TS,'unixepoch')` must match libc-style UTC date fields for `%d,%e,%F,%H,%k,%I,%l,%j,%m,%M,%u,%w,%W,%Y,%%,%P,%p,%U,%V,%G,%g`.
- Focused PHP test: `lanes/libsqlite/tests/SQLiteRealUpstreamCorpusDateAffinityDynamicDate4Rows2300To3299Test.php`.
- Focused result: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusDateAffinityDynamicDate4Rows2300To3299Test.php` passed with `1 test files, 6014 assertions, 0 failures`.
- Non-overlap: owns upstream date4 rows `2300..3299`; avoids the accepted date4 rows `300..2299` and existing `date.test` date-2/date-3/date-5/date-11/date-13/date-19 clusters.
- Dependency closure: no new support component needed; this reuses the existing native `SQLiteCoreScalarFunction` date/time implementation.
