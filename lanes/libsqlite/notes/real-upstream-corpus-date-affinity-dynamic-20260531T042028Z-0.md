# real-upstream-corpus-date-affinity-dynamic-20260531T042028Z-0

- Base accepted HEAD: `5823f556f77d50bd49ce909acb22097fc44da229`.
- Upstream source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/date4.test`.
- Owned non-overlapping range: `date4.test` generated `date4-15400` through `date4-16399`.
- Behavior: `SELECT strftime($::FMT,$::TS,'unixepoch')` parity for the Linux libc-compatible format `%d,%e,%F,%H,%k,%I,%l,%j,%m,%M,%u,%w,%W,%Y,%%,%P,%p,%U,%V,%G,%g`, including integer and text unixepoch timestamp affinity paths.
- Focused growth: `1004` TestRunner PASS cases in `SQLiteRealUpstreamCorpusDateAffinityDynamicDate4Rows15400To16399Test.php`.
- Non-overlap: avoids accepted date4 rows `0..15399`, date/date2/date3/date5 modifier coverage, and affinity comparison/type matrix coverage already present in the current base.
- Dependency closure: no new support component needed; reuses `SQLiteCoreScalarFunction` strftime/unixepoch dispatch and lane-local date4 expected-value helpers.
