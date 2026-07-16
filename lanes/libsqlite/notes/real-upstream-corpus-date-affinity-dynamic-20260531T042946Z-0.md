# real-upstream-corpus-date-affinity-dynamic-20260531T042946Z-0

Base accepted HEAD: `9c639ff85ec75b07f4dd143b6bbb0e832cdb6a85`

Added a focused real-upstream corpus test file for `date.test` extended
`strftime()` specifier behavior:

- `date.test` `date-3.20` `%e`
- `date.test` `date-3.21` `%F %T`
- `date.test` `date-3.22` `%k`
- `date.test` `date-3.23` through `date-3.29` `%I`, `%P`, `%p`, and `%l`
- `date.test` `date-3.30` `%F %R`
- `date.test` `date-3.31` through `date-3.37` `%w %u`
- `affinity2.test` source citation for typed date/affinity corpus continuity

Focused assertion growth:

- New focused PHP TestRunner cases: `1020` PASS lines
- New behavior assertions: `6065`
- Upstream files cited: `/home/claude/port-libs/.upstream-cache/libsqlite/test/date.test`
  and `/home/claude/port-libs/.upstream-cache/libsqlite/test/affinity2.test`

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusDateAffinityDynamicStrftimeExtended20260531T042946ZTest.php`
  - `1 test files, 6065 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamDateAffinityDynamicSchemaTest.php lanes/libsqlite/tests/SQLiteRealUpstreamDate2AffinityDynamicBatchTest.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusDateAffinityDynamicInvalidStrftime20260531T032606ZTest.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusDateAffinityDynamicComponentValidation20260531T034409ZTest.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusDateAffinityDynamicStrftimeExtended20260531T042946ZTest.php`
  - `5 test files, 34618 assertions, 0 failures`

Dependency closure: no new support component needed. This reuses the existing
native `SQLiteCoreScalarFunction::strftime()` implementation and the existing
upstream date/affinity corpus helpers.

Non-overlap: this covers `date.test` `3.20..3.37` extended `strftime()`
specifier output and dynamic weekday/hour/date alias checks. It does not repeat
the existing component-validation slice's `date-1.18`, `date-1.24..1.29`, or
`date-2.26..2.50` modifier corpus, nor the existing `date2` schema/index
determinism batch.
