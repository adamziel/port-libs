# real-upstream-corpus-date-affinity-dynamic-20260530T231733Z-0

Added `SQLiteRealUpstreamDateTimediffDynamicMatrixTest.php` as an additive real upstream date/affinity corpus batch.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/timediff1.test`
- Scenario ranges: `timediff-3.1`, `timediff-3.3`, and `timediff-5-1..5-20`.

Behavior added:

- Extends the native date/time modifier parser to accept SQLite timediff-style modifiers of the form `+YYYY-MM-DD HH:MM`, `+YYYY-MM-DD HH:MM:SS`, and `+YYYY-MM-DD HH:MM:SS.SSS`.
- Preserves upstream `timediff1.test` rejection behavior for month `12`, day `31`, dangling time fields, malformed fractional seconds, and suffix garbage.
- Adds 1200 generated positive timediff-style modifier cases over distinct year/month/day/hour/minute/second combinations using the same `timediff-5` grammar.
- Includes a generic application schedule roundtrip sample with `key_name` labels and no domain-specific API names.

Verification:

- `php -l lanes/libsqlite/src/SQLiteCoreScalarFunction.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamDateTimediffDynamicMatrixTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamDateTimediffDynamicMatrixTest.php`
  - `1 test files, 1229 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - `1 test files, 3 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteEncodingSourceNeutralDefaultsTest.php lanes/libsqlite/tests/SQLiteTenantSavepointWalSourceNeutralTest.php`
  - `2 test files, 2 assertions, 0 failures`
- `git diff --check -- lanes/libsqlite`
  - passed with no output

Non-overlap:

- This does not repeat accepted `date.test`, `date2.test`, `date3.test`, `date4.test`, `date5.test`, floor/ceiling, subsecond, boundary, localtime/utc, fraction truncation, or existing small `timediff()` scalar coverage.
- The new source behavior is the full partial timediff modifier grammar from upstream `timediff1.test` section 5.
- The broader upstream `timediff-4` and `timediff-6` roundtrip matrices are not claimed here; a red-first attempt showed they still need a deeper calendar-diff algorithm fix for negative month-edge diffs and very wide historical/future ranges.

Dependency closure:

- No new support component is needed. The slice reuses `SQLiteCoreScalarFunction` date/time parsing and native PHP calendar arithmetic.
