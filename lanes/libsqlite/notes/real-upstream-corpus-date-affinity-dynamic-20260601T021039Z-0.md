# real-upstream-corpus-date-affinity-dynamic-20260601T021039Z-0

Added `SQLiteRealUpstreamCorpusDateAffinityDynamicNowModifier20260601T021039ZTest.php` as an additive real upstream date/time corpus batch.

Source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/date.test`
- Scenario: `date-8.1..8.90`, where upstream fixes `sqlite_current_time` at `2003-10-22 12:34:00` and verifies `datetime('now', ...)` modifiers preserve the statement clock while applying weekday, start-of, day/month/year, minute/hour/second, and invalid-modifier behavior.

Focused behavior:

- Ports all upstream `date.test` section 8 cases against `SQLiteCoreScalarFunction::statementDateTimeResults()`.
- Adds 768 generated statement-clock rows to prove the same `now` instant feeds `datetime`, `date`, `time`, and `strftime` through deterministic modifier dispatch.
- Exercises a generic application retention schedule rollup with neutral `key_name` labels.

Non-overlap:

- This owns `date.test` section 8 `now` modifier behavior.
- It avoids accepted `date4` row-loop shards, `date15` statement-stability rows, `date19` floor/ceiling, `date20` fractional truncation, `date3` auto/unixepoch, timezone/localtime chains, timediff matrices, and expression-affinity shards.
- Expected dashboard movement is focused TestRunner growth only: `phpPass` `5329602 -> 5335855` if accepted alone. Mapped denominator remains `1589 / 1589`.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusDateAffinityDynamicNowModifier20260601T021039ZTest.php` - passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusDateAffinityDynamicNowModifier20260601T021039ZTest.php` - `1 test files, 6253 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusDateAffinityDynamicNowModifier20260601T021039ZTest.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusDateAffinityDynamicCurrentDateStep20260531T071019ZTest.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusDateAffinityDynamicTimeOnlyDefault20260531T064107ZTest.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php` - `4 test files, 29600 assertions, 0 failures`.
- `php -r 'json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'` - passed.
- `git diff --check -- lanes/libsqlite` - passed.

Root harness:

- Not run - isolated micro-slice.

Dependency closure:

- No new support component is needed. The slice reuses existing native statement-clock replacement and date/time scalar modifier dispatch in `SQLiteCoreScalarFunction`.
