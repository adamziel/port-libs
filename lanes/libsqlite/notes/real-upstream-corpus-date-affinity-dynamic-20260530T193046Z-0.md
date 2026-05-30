# real-upstream-corpus-date-affinity-dynamic-20260530T193046Z-0

Status: ready, focused real upstream corpus growth.

Upstream source:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/date.test`
- Ported `date-2.2c-0..999`: one thousand `strftime('%H:%M:%f', 1237962480.NNN, 'unixepoch')` millisecond-preservation cases.

Coverage:

- Adds 1,000 distinct TestRunner PASS cases to `SQLiteRealUpstreamDateAffinityDynamicCorpusTest.php`.
- Exercises the existing native `SQLiteCoreScalarFunction` unixepoch numeric-affinity path, fractional timestamp parsing, and `%f` millisecond formatting.
- Non-overlap: existing accepted date-affinity coverage in this file covered selected `date.test` scalar cases, `date2.test` deterministic schema/date-index behavior, `date4.test` libc parity ranges, and `date5.test` Gregorian-cycle pairs. It did not include the upstream `date.test` `date-2.2c` millisecond matrix.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamDateAffinityDynamicCorpusTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamDateAffinityDynamicCorpusTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
- `git diff --check -- lanes/libsqlite`

Dependency closure:

- No new support component is needed. The slice reuses existing native date/time scalar support in `SQLiteCoreScalarFunction`.

Root harness:

- Not run; isolated micro-slice.
