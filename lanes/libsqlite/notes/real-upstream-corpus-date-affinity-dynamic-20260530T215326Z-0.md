# real-upstream-corpus-date-affinity-dynamic-20260530T215326Z-0

Status: focused real-upstream corpus PASS growth for SQLite date/time modifier
placement semantics.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/date3.test`
- `date3-3.1` and `date3-3.2`: `unixepoch` only works when it immediately
  follows the numeric time value.
- `date3-4.1` through `date3-4.3`: `julianday` only works when it immediately
  follows a numeric Julian-day time value.
- `date3-2.30`: `auto` is a no-op for text time-values.

Focused PHP coverage:

- Added `SQLiteRealUpstreamDate3ModifierPlacementDynamicTest.php`.
- Ports the date3 modifier-placement behavior as an oracle-backed dynamic
  corpus over 150 numeric time values, seven immediate/delayed modifier
  placements, and five text time-values.
- Each generated PASS case compares native PHP `SQLiteCoreScalarFunction`
  behavior with local `sqlite3` for `datetime`, `date`, `time`, and
  `strftime('%Y-%m-%d %H:%M:%S', ...)`.
- Focused run: `1 test files / 5315 assertions / 0 failures / 1066 PASS
  lines`.

Non-overlap:

- Existing date-affinity dynamic coverage already covers broad `date.test`,
  `date2.test`, `date3` auto-boundary/unixepoch roundtrip, `date4.test`, and
  `date5.test` batches.
- This slice owns the `date3.test` modifier-placement matrix for
  `unixepoch`, `julianday`, and delayed `auto` placement. It does not add
  metadata-only runner rows, generated fake upstream ids, WordPress-shaped APIs,
  JSON/PRAGMA/VFS/WAL/B-tree behavior, or source-neutral cleanup.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamDate3ModifierPlacementDynamicTest.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamDate3ModifierPlacementDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamDate3ModifierPlacementDynamicTest.php`
  - `1 test files, 5315 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - run as final guard for generic API enforcement.
- `git diff --check -- lanes/libsqlite`
  - run as final whitespace guard.

Dependency closure:

- No new support component is needed. The slice reuses the native
  `SQLiteCoreScalarFunction` date/time implementation and local `sqlite3` only
  as an oracle for focused upstream parity tests.
