# real-upstream-corpus-date-affinity-dynamic-20260530T181130Z-0

Extended the existing real upstream date affinity corpus to the full generated
`date4.test` strftime parity range.

Upstream source:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/date4.test`
- `date4-1536` through `date4-24858`: generated libc/SQLite `strftime()`
  parity for timestamps `i * 86390` with the Linux format matrix
  `%d,%e,%F,%H,%k,%I,%l,%j,%m,%M,%u,%w,%W,%Y,%%,%P,%p,%U,%V,%G,%g`.

Focused impact:

- Adds 23,323 distinct focused TestRunner PASS cases by extending the prior
  accepted `date4-0..1535` range to the upstream file's `date4-24858` bound.
- Focused verification passed
  `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamDateAffinityDynamicCorpusTest.php`
  with `1 test files / 25831 assertions / 0 failures`.
- No mapped denominator movement is claimed; this is real upstream behavior
  expansion inside an already mapped date corpus surface.

Non-overlap:

- Does not repeat the prior accepted `date4-0..1535` rows, date2 deterministic
  schema-use guards, expression affinity rows, window corpus rows, JSON/VFS/WAL
  batches, runner metadata, or generated fake suite rows.
- The new owned range is exactly upstream `date4-1536..24858`.

Dependency closure:

- No new support component is needed. This reuses the native
  `SQLiteCoreScalarFunction::strftime()` implementation and PHP UTC
  `DateTimeImmutable` formatting oracle already used by the accepted date
  corpus.
