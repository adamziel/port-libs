# real-upstream-corpus-date-affinity-dynamic-20260530T235701Z-0

Base accepted HEAD: `d045774aa6bf87ca954fff751277766f57e01075`.

This slice ports a non-overlapping real upstream SQLite date cluster from
`/home/claude/port-libs/.upstream-cache/libsqlite/test/date.test`:

- `date-6.25.1` through `date-6.25.7`: explicit `Z`, `+00:00`, and `-00:00`
  suffixes behave like UTC.
- `date-6.26` and `date-6.27`: explicit timezone suffixes make later `utc`
  modifiers no-ops.

Added focused PHP coverage:

- `SQLiteRealUpstreamCorpusDateAffinityDynamicUtcSuffix20260530T235701ZTest.php`
- 1001 distinct TestRunner PASS cases.
- 3004 focused assertions.
- Expected rows are hydrated from local `sqlite3` oracle queries over a 1000-row
  timestamp/suffix/modifier matrix.

Non-overlap:

- Does not repeat existing `date.test` `date-2.2c` unixepoch millisecond rows.
- Does not repeat `date.test` `date-3.11` Julian week rows.
- Does not repeat `date3.test` auto/unixepoch roundtrip rows.
- Does not repeat `date.test` date-13 modifier arithmetic, date-16 boundary,
  date-17 start modifiers, date-19 floor/ceiling, or date5 Gregorian cycle
  batches.

Dependency closure:

- No new support component needed.
- Reuses `SQLiteCoreScalarFunction` date/time parsing plus the existing focused
  PHP TestRunner and local sqlite3 oracle pattern.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusDateAffinityDynamicUtcSuffix20260530T235701ZTest.php`
  - `1 test files, 3004 assertions, 0 failures`
