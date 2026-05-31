# real-upstream-corpus-date-affinity-dynamic-20260531T093939Z-0

Added `SQLiteRealUpstreamCorpusDateAffinityDynamicLongStrftime20260531T093939ZTest.php` as an additive real upstream date/affinity corpus shard.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/date.test`
- Scenario names: `date-3.16` and `date-3.17`.

Focused behavior:

- 1,000 distinct long `strftime()` format expansion cases derived from upstream `repeat 200 %Y` and `repeat 200 abc%m123`.
- Each case checks native `strftime()` output, byte length, prefix/suffix stability, result `typeof()`, and TEXT-affinity storage preservation.
- Generic retention rollup samples long formatted values without introducing application-domain-specific APIs or fixtures.

Focused verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusDateAffinityDynamicLongStrftime20260531T093939ZTest.php`
- Result: no syntax errors.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusDateAffinityDynamicLongStrftime20260531T093939ZTest.php`
- Result: `1 test files, 7014 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
- Result: `1 test files, 3 assertions, 0 failures`.
- `php -r '$p="lanes/libsqlite/lane-status.json"; json_decode(file_get_contents($p), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok".PHP_EOL;'`
- Result: `lane-status json ok`.
- `git diff --check -- lanes/libsqlite`
- Result: passed with no output.

Expected movement:

- `+1003` focused TestRunner cases and `+7014` focused assertions.
- Mapped denominator remains unchanged because `date.test` is already present in the upstream manifest; this is PHP PASS/assertion growth over hydrated real upstream behavior.

Non-overlap:

- Owns `date.test` `date-3.16`/`date-3.17` long repeated-format expansion.
- Avoids accepted date4 row ranges, date2 fractional unixepoch rows, invalid strftime conversion NULL behavior, date5 Gregorian-cycle rows, and expression-affinity CASE/cast/storage shards.

Dependency closure:

- No new support component is needed. The slice reuses native `SQLiteCoreScalarFunction` `strftime()` dispatch and `SQLiteRealExpressionAffinityCorpusPlan` TEXT-affinity storage behavior.

Blocker / next task:

- No blocker found for this shard. A next date/affinity worker should avoid `date-3.16`/`date-3.17` and continue only with a genuinely uncovered upstream section or a behavior fix that unlocks broader runner admission.
