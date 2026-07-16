# real-upstream-corpus-date-affinity-dynamic-20260530T170351Z-0

Implemented a focused upstream `date.test` behavior slice for signed and unsigned `HH:MM[:SS]` date-time modifiers.

Upstream source:
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/date.test`
- Ported scenarios: `date-11.1` through `date-11.9`

Changed behavior:
- `SQLiteCoreScalarFunction` now applies `-01:20:30`, `+12:30:00`, `+12:30`, unsigned `12:30`, and adjacent `HH:MM` elapsed-time modifiers before the named-unit modifier path.
- The focused corpus adds 9 direct upstream PASS cases plus 1 generic application elapsed-window summary.

Verification:
- `php -l lanes/libsqlite/src/SQLiteCoreScalarFunction.php` passed.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamDateAffinityDynamicCorpusTest.php` passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamDateAffinityDynamicCorpusTest.php` passed: `1 test files, 1122 assertions, 0 failures`.
- `git diff --check -- lanes/libsqlite` passed.

Dashboard expectation:
- Focused PASS delta: `+10` over the existing 1112-case date-affinity dynamic corpus.
- `phpPass`: `201224 -> 201234`.
- Mapped coverage: unchanged at `958 / 1589`; this ports additional behavior within already mapped upstream `date.test`.

Dependency closure:
- No new support component is needed. This reuses the existing native PHP date/time scalar function path.

Non-overlap:
- Does not repeat existing unixepoch, weekday, timezone, standalone time, strftime, or `date4.test` libc-parity coverage already present in `SQLiteRealUpstreamDateAffinityDynamicCorpusTest.php`.
