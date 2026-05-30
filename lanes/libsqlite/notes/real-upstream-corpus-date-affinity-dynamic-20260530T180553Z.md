# real-upstream-corpus-date-affinity-dynamic-20260530T180553Z

Base accepted HEAD: `70cbf38e6a31c3f41f86a2057096cb0006d09cf6`.

This slice ports real upstream SQLite behavior from:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/date.test`
  - `date-2.2c-0..399`: fractional Unix timestamp `strftime('%H:%M:%f', ..., 'unixepoch')` milliseconds.
  - `date-2.3..2.51`: weekday, start-of, amount modifier, and invalid modifier `NULL` behavior.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/affinity2.test`
  - `affinity2-100..150`: inserted storage classes for INTEGER, REAL, BLOB, NUMERIC, and TEXT affinity columns.
  - `affinity2-200..300`: comparison affinity behavior for column and unary-plus expressions.
  - `affinity2-500..507`: unary blob/text numeric comparison ticket coverage.

Behavior change:

- `SQLiteCoreScalarFunction` now returns `NULL` for unsupported date/time modifiers instead of throwing, matching upstream `date.test` invalid modifier cases.

Focused evidence:

- Red before source fix: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamDateAffinityModifierDynamicCorpusTest.php` produced `1 test files, 582 assertions, 21 failures`, all from invalid date modifier `NULL` parity and one affinity fixture expectation.
- Green after fix: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamDateAffinityModifierDynamicCorpusTest.php` produced `1 test files, 602 assertions, 0 failures`.

Dashboard movement:

- Adds one real upstream PHP TestRunner file with 602 focused assertions/PASS lines.
- Does not claim new mapped denominator rows; the source truth is existing hydrated upstream `.test` files.

Dependency closure:

- No new support component is needed. The slice reuses existing native scalar date/time and affinity comparison helpers.

Non-overlap:

- Does not repeat accepted `date3.test` auto/unixepoch coverage, date4 strftime samples, expression affinity hex/text behavior, or source-neutral CAST/LIKE/GLOB cleanup. This slice owns earlier `date.test` fractional Unix timestamp and invalid modifier behavior plus `affinity2.test` comparison/storage cases.
