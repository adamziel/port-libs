# real-upstream-corpus-date-affinity-dynamic-20260530T202713Z-0

Status: ready for integration.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/date.test`
- `date-15.1`: multiple `julianday('now')` calls in one `sqlite3_step()` return the same value even with a sleeper function between them.
- `date-15.2`: multiple `current_timestamp` evaluations in one `sqlite3_step()` return the same value.

Focused PHP coverage:

- Added `SQLiteCoreScalarFunction::statementDateTimeResults()` for step-scoped date/time evaluation with one shared UTC `now` instant.
- Added `current_date`, `current_time`, and `current_timestamp` scalar dispatch.
- Added `SQLiteRealUpstreamDateStatementNowDynamicCorpusTest.php` with 1,002 focused PASS cases and 5,003 assertions.

Non-overlap:

- This slice owns only upstream `date.test` `date-15.1` and `date-15.2` statement-stable `now` behavior.
- It does not repeat accepted date2 determinism/schema guards, date3 auto/unixepoch, date4 libc formatting, date5 Gregorian cycles, date floor/ceiling month matrices, boundary overflow, fraction truncation, expression affinity, or metadata-only runner rows.
- Expected dashboard movement: PASS-line growth only, +1,002 focused PHP PASS cases. No mapped denominator change claimed.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamDateStatementNowDynamicCorpusTest.php`
  - `1 test files, 5003 assertions, 0 failures`

Dependency closure:

- No new support component is needed. The slice reuses existing native `SQLiteCoreScalarFunction` date/time parsing and adds a bounded step-scoped evaluation helper for SQLite's statement-stable `now` semantics.
