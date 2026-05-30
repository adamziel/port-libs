# real-upstream-corpus-date-affinity-dynamic-20260530T203444Z-0 blocked

Base accepted HEAD: `d5feb4b8c9f51e52c1a4ee4e369261ca23aa819e`.

Assigned upstream domain attempted:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/date.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/date2.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/date3.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/date4.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/date5.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/affinity2.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/affinity3.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/types2.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/types3.test`

Current-base overlap found:

- `SQLiteRealUpstreamDateAffinityDynamicCorpusTest.php` already ports the main `date.test` julianday, modifier, timezone, strftime, fractional-unixepoch, `date4.test`, and `date5.test` cycle families.
- `SQLiteRealUpstreamDateAffinityDynamicNextCorpusTest.php` already ports the `date.test date-2.2c` fractional unixepoch matrix plus `affinity2.test`, `affinity3.test`, and `types3.test` affinity cases.
- `SQLiteRealUpstreamDateAffinityDynamicUtcNullTest.php` already ports `date.test` invalid timezone, explicit UTC suffix/offset, `date-6.27`, and `date-7.1..7.16` NULL propagation.
- `SQLiteRealUpstreamDateAffinityDynamicModifierBatchTest.php`, `SQLiteRealUpstreamDateBoundaryDynamicCorpusTest.php`, `SQLiteRealUpstreamDateFloorCeilingDynamicCorpusTest.php`, `SQLiteRealUpstreamDateFloorCeilingMonthMatrixCorpusTest.php`, `SQLiteRealUpstreamDateFractionTruncationDynamicCorpusTest.php`, `SQLiteRealUpstreamDateFractionalUnixepochCorpusTest.php`, `SQLiteRealUpstreamDateStrftimeExtendedDynamicTest.php`, and `SQLiteRealUpstreamDate5GregorianCycleCorpusTest.php` cover the remaining high-yield date modifier, boundary, floor/ceiling, fraction, extended strftime, and Gregorian-cycle areas.
- `SQLiteRealUpstreamExpressionAffinity2DynamicTest.php`, `SQLiteRealUpstreamExpressionAffinity3DynamicTest.php`, `SQLiteRealUpstreamExpressionAffinityDynamicTypes2MatrixTest.php`, `SQLiteRealUpstreamExpressionAffinityTypes2OracleDynamicTest.php`, and `SQLiteRealUpstreamCorpusExpressionAffinityDynamicFollowupTest.php` cover the high-yield affinity comparison/storage/operator matrix from `affinity2.test`, `affinity3.test`, `types2.test`, and `types3.test`.

Why no ready patch was emitted:

The hard handoff floor for `real-upstream-corpus-*` requires at least 1,000 distinct focused TestRunner PASS cases, 5,000 real behavior assertions, a named blocker fix that unlocks at least 2,000 PASS cases or 10,000 assertions, or mapped denominator movement with guarded upstream-runner evidence. The remaining obvious `date-affinity-dynamic` candidates on this base are either already covered by accepted focused files or are localtime/OS-clock dependent cases that need a broader deterministic localtime harness before they can honestly unlock a large batch. Adding a small duplicate PHP test file here would inflate marker volume without non-overlapping behavior.

Next larger batch to try:

Build a deterministic localtime/UTC fault harness for `date.test date-6.1..6.24` and `date-6.28..6.32`, then port those sections as one focused runner. That batch should explicitly model the upstream `sqlite3_test_control SQLITE_TESTCTRL_LOCALTIME_FAULT` behavior and timezone conversion expectations instead of relying on the host timezone. If implemented centrally in `SQLiteCoreScalarFunction` or a focused date-time helper, it can unlock the skipped localtime/UTC cases plus future `localtime` date corpus rows without duplicating the already accepted UTC-offset and NULL-propagation coverage.

Verification performed for this blocker note:

- `rg -n "date|affinity|dynamic" lanes/libsqlite/tests lanes/libsqlite/notes lanes/libsqlite/src`
- `find /home/claude/port-libs/.upstream-cache/libsqlite/test -maxdepth 1 -type f \( -name '*date*.test' -o -name '*affinity*.test' -o -name 'e_expr.test' -o -name 'types*.test' \) -printf '%f\n' | sort`
- `rg -n "date-6\.2[7-9]|date-6\.3[0-2]|date-13|date-14|date-15" lanes/libsqlite/tests/SQLiteRealUpstreamDate* lanes/libsqlite/tests/*Date*`
- `rg -n "affinity2-(200|210|220|300)|affinity2.* 4[0-9]0|affinity2.*50[0-7]|affinity2.*60[0-1]" lanes/libsqlite/tests/SQLiteRealUpstream*Affinity* lanes/libsqlite/tests/*Affinity*`

Dependency closure:

No new support component is needed for the overlap audit. The follow-up localtime batch would reuse native PHP date/time support and add a deterministic test-control-style shim only if the accepted API needs to model upstream localtime faults without depending on the host environment.
