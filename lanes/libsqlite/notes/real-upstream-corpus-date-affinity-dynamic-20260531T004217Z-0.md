# real-upstream-corpus-date-affinity-dynamic-20260531T004217Z-0

Base accepted HEAD: `ad16a572f80ccf85246d93f3ad58ce0402786c09`.

Added focused real-upstream coverage in
`SQLiteRealUpstreamCorpusDateAffinityDynamicModifierClock20260531T004217ZTest.php`.
Source truth is `/home/claude/port-libs/.upstream-cache/libsqlite/test/date.test`:

- `date-11.1` through `date-11.10`: `HH:MM` and `HH:MM:SS` datetime modifiers.
- `date-13.11` through `date-13.24`: fractional day/hour/minute/second/month/year `julianday()` modifiers.
- `date-13.30` through `date-13.37`: fractional-year `date()` modifiers and normalized overflow dates.

Focused verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusDateAffinityDynamicModifierClock20260531T004217ZTest.php`
- Result: `1 test files, 16102 assertions, 0 failures`, with `8026` distinct PASS lines.

Non-overlap:

- This slice avoids accepted date4/date5 Gregorian-cycle, UTC suffix, strftime extended, unixepoch fraction, statement-now, and date2 index/schema rows.
- It exercises date.test clock modifier parsing plus fractional modifier arithmetic, and includes a generic application retention schedule using `setting_id`/`key_name` terminology.

Dependency closure:

- No new support component is needed. The slice reuses `SQLiteCoreScalarFunction` date/time behavior and the existing focused PHP test harness.
