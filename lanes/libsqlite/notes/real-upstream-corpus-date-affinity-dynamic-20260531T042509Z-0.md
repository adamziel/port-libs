# real-upstream-corpus-date-affinity-dynamic-20260531T042509Z-0

- Base accepted HEAD: `5823f556f77d50bd49ce909acb22097fc44da229`.
- Upstream source: `/home/claude/port-libs/.upstream-cache/libsqlite/test/date.test`.
- Ported cluster: `date.test` `date-5.1` through `date-5.15` timezone offset and Zulu suffix normalization, plus a generated 1000-row nonzero offset matrix derived from the same upstream behavior.
- Focused test file: `lanes/libsqlite/tests/SQLiteRealUpstreamCorpusDateAffinityDynamicTimezoneOffset20260531T042509ZTest.php`.
- New focused PASS cases verified: 1017 test cases. Assertions verified: 4034 assertions.
- Non-overlap: avoids accepted date4 row ranges through 14300-15399, date3 auto/unixepoch, date6 UTC suffix/localtime-chain, date floor/ceiling, component-validation, invalid-strftime, fractional truncation, and expression-affinity clusters. This slice covers nonzero timezone offsets and invalid mixed timezone suffixes from `date.test` section 5.
- Dependency closure: no new support component is needed; the existing `SQLiteCoreScalarFunction` date/time parser and formatter handles the upstream behavior.
