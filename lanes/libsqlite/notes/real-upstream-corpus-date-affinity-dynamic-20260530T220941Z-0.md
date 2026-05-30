# real-upstream-corpus-date-affinity-dynamic-20260530T220941Z-0

Status: ready.

Upstream source:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/date.test`
- `date-13.11` through `date-13.18`: ticket #3618 Julian-day modifier arithmetic for positive/negative day, fractional day, hour, and minute modifiers.

Focused coverage:

- Added `SQLiteRealUpstreamDateAffinityJulianModifierBulkTest.php`.
- The file generates 1,000 distinct TestRunner cases from 125 Julian-day values crossed with the eight real upstream `date-13.11..13.18` modifier families.
- Expected `julianday()` and `datetime()` quote/type pairs are hydrated once from local `sqlite3`; assertions compare those oracle values to the native PHP `SQLiteCoreScalarFunction` implementation.
- Focused result: `1 test files, 6007 assertions, 0 failures`, with 1,001 PASS lines.

Non-overlap:

- This does not repeat accepted `date2` deterministic schema guards, `date3` auto/unixepoch boundary and random roundtrip coverage, `date4` strftime libc parity, `date5` Gregorian cycle coverage, broad CAST affinity matrices, or prior `date.test date-2.2c` fractional unixepoch coverage.
- The owned surface is `date.test` ticket #3618 Julian-day arithmetic over positive/negative and fractional modifiers.

Dependency closure:

- No new support component is needed. The slice reuses the existing native PHP date/time scalar implementation and the already-available local `sqlite3` oracle path used by other real upstream corpus tests.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamDateAffinityJulianModifierBulkTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamDateAffinityJulianModifierBulkTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
- `git diff --check -- lanes/libsqlite`
