# real-upstream-corpus-date-affinity-dynamic-20260531T070511Z-0

Scope: real upstream SQLite date/affinity corpus, focused on `date3.test`.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/date3.test`
- `date3-2.40`: `datetime(timeval,'auto') == datetime`
- `date3-3.1` / `date3-3.2`: `unixepoch` modifier must immediately follow the numeric time value.
- `date3-4.1` through `date3-4.3`: `julianday` modifier must immediately follow a numeric Julian time value.
- `date3-5.0`: first 63 days of 1970 are ambiguous under `auto` and are interpreted as Julian day numbers.

Patch:

- Added `SQLiteRealUpstreamCorpusDateAffinityDynamicAutoUnixepoch20260531T070511ZTest.php`.
- Ports 1,000 independent generated rows over Unix-day offsets around 1970.
- Uses the local `sqlite3` CLI as an oracle for expected `datetime(...,'auto')`, `datetime(...,'unixepoch')`, `datetime(...,'julianday')`, and modifier-order behavior.
- Exercises existing native `SQLiteCoreScalarFunction` date/time behavior. No new production API or support component was needed.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusDateAffinityDynamicAutoUnixepoch20260531T070511ZTest.php`
- Result: `1 test files, 7011 assertions, 0 failures`
- PASS-line growth: 1002 distinct TestRunner cases.

Non-overlap:

- Does not repeat accepted `date4` strftime rows, Julian-week fractional rows, time-only defaults, extended `strftime` format coverage, or date timezone-offset behavior.
- This slice owns the `date3.test` `auto`/`unixepoch`/`julianday` modifier-order and first-63-days ambiguity cluster.

Dependency closure:

- Reuses existing bounded native PHP date/time scalar support.
- No new support component is needed.
