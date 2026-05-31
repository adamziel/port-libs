# real-upstream-corpus-date-affinity-dynamic-20260531T040135Z-0

Added `SQLiteRealUpstreamDate3AutoUnixepochDynamicCorpusTest.php` as an additive real upstream date/affinity corpus batch.

Source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/date3.test`
- Scenario names: `date3-1.1..1.8`, `date3-2.1..2.40`, `date3-3.1..3.2`, `date3-4.1..4.3`, and `date3-5.0`.

Focused behavior:

- Ports `unixepoch()` boundary behavior, including negative timestamps, 32-bit rollover, max/min SQLite calendar endpoints, and millisecond truncation.
- Expands the upstream `date3-1.7` randomized integer Unix timestamp identity property into 1000 deterministic reproducible PHP TestRunner cases.
- Ports `auto` modifier Julian-day-versus-Unix-timestamp dispatch, out-of-range NULL handling, text-value no-op behavior, and mixed text/Julian/Unix timestamp rows.
- Ports immediate-placement rules for `unixepoch` and `julianday` modifiers.
- Ports the `date3-5.0` first-63-days-of-1970 ambiguity as 111 focused offset cases.
- Adds one generic application retention sample for mixed timestamp affinity with no domain-specific API names.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamDate3AutoUnixepochDynamicCorpusTest.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamDate3AutoUnixepochDynamicCorpusTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamDate3AutoUnixepochDynamicCorpusTest.php`
  - `1 test files, 1139 assertions, 0 failures`
  - `1139` focused TestRunner PASS cases.

Non-overlap:

- This slice targets real upstream `date3.test` unixepoch/auto/julianday modifier and first-63-days ambiguity behavior.
- It does not repeat accepted `date.test` scalar modifier coverage, `date2.test` deterministic schema/index guard behavior, `date4.test` strftime rows, `date5.test` Gregorian Julian-day roundtrips, or expression-affinity `types2` behavior.
- Countable movement is focused PHP PASS-line growth only; mapped denominator coverage is unchanged because the upstream manifest is already fully mapped.

Dependency closure:

- No new support component is needed. The slice reuses native `SQLiteCoreScalarFunction` date/time parsing, Julian-day conversion, Unix timestamp conversion, and modifier dispatch.
