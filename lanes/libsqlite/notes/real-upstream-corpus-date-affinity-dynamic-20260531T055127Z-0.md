# real-upstream-corpus-date-affinity-dynamic-20260531T055127Z-0

Status: additive real upstream date/affinity corpus coverage.

Upstream source:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/date4.test`
- Scenario: generated `date4.test` loop `date4-20400` through `date4-21399`, executing `SELECT strftime($::FMT,$::TS,'unixepoch');` with `TS = i * 86390`.

Added focused PHP test:

- `lanes/libsqlite/tests/SQLiteRealUpstreamCorpusDateAffinityDynamicDate4Rows20400To21399Test.php`

Focused behavior:

- 1,000 distinct upstream row cases for the next non-overlapping `date4.test` range.
- Each row checks native `strftime()` parity for integer, text, and REAL Unix timestamp inputs.
- Each row also verifies text storage-class output, comma-field shape, date-prefix stability, and ISO-year suffix stability.
- The generic rollup uses `app_settings`-style `key_name` rows and adds no domain-specific API.

Non-overlap:

- Current accepted coverage already includes `date4.test` rows through `19400..20399`.
- This slice owns only `date4.test` rows `20400..21399`.
- It avoids accepted `date.test` modifier coverage, `date2.test` deterministic date guards, `date3.test` auto/unixepoch ambiguity coverage, `date5.test` Gregorian cycle coverage, and expression-affinity/cast/type-matrix batches.

Dependency closure:

- No new support component is needed; this reuses existing native `SQLiteCoreScalarFunction` `strftime()` / `unixepoch` support and the hydrated upstream SQLite checkout as source truth.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusDateAffinityDynamicDate4Rows20400To21399Test.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusDateAffinityDynamicDate4Rows20400To21399Test.php`
- `git diff --check -- lanes/libsqlite`
