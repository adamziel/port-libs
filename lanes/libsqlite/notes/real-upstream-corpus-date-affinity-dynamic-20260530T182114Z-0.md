# real-upstream-corpus-date-affinity-dynamic-20260530T182114Z-0

Base accepted HEAD: `1be884bec4b3d8944d386430e62bb83a7a09f0ef`.

This slice ports real upstream SQLite behavior from:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/date.test`
  - `date-19.1..19.32`: `floor` and `ceiling` modifiers for invalid calendar-day normalization.
  - `date-19.40..19.53`: `floor` and `ceiling` after ambiguous month/year shifts, including signed `YYYY-MM-DD` modifiers.

Behavior change:

- `SQLiteCoreScalarFunction` now preserves SQLite's default ceiling behavior while tracking the corresponding floor candidate for ambiguous date parses and month/year shifts.
- Date modifiers now accept signed `YYYY-MM-DD` shift modifiers used by upstream `date.test` section 19.

Focused evidence:

- `php -l lanes/libsqlite/src/SQLiteCoreScalarFunction.php` passed.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamDateFloorCeilingDynamicCorpusTest.php` passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamDateFloorCeilingDynamicCorpusTest.php` passed: `1 test files, 1275 assertions, 0 failures`.
- Date-family regression check passed: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamDateAffinityDynamicCorpusTest.php lanes/libsqlite/tests/SQLiteRealUpstreamDateAffinityModifierDynamicCorpusTest.php lanes/libsqlite/tests/SQLiteRealUpstreamDateAutoUnixepochModifierCorpusTest.php lanes/libsqlite/tests/SQLiteRealUpstreamDate2DeterministicDynamicCorpusTest.php lanes/libsqlite/tests/SQLiteRealUpstreamDate2DeterministicSchemaGuardCorpusTest.php lanes/libsqlite/tests/SQLiteRealUpstreamDateFractionalUnixepochCorpusTest.php lanes/libsqlite/tests/SQLiteRealUpstreamDateFloorCeilingDynamicCorpusTest.php` produced `7 test files, 8659 assertions, 0 failures`.

Dashboard movement:

- Adds one real upstream PHP TestRunner file with 1275 distinct focused PASS cases/assertions.
- Does not claim new mapped denominator rows; this is behavior-backed PHP corpus growth from an already hydrated upstream `.test` file.

Dependency closure:

- No new support component is needed. The slice reuses the native PHP date/time scalar implementation.

Non-overlap:

- Does not repeat existing date2 deterministic checks, date3 auto/unixepoch placement, date4 strftime parity, date5 leap-cycle conversion, or prior invalid date-modifier `NULL` coverage.
- This slice owns `date.test` section 19 floor/ceiling ambiguity behavior only.
