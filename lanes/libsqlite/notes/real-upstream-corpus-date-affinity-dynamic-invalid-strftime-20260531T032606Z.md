# Real Upstream Corpus: Date Affinity Dynamic Invalid Strftime

- Slice: `real-upstream-corpus-date-affinity-dynamic-20260531T032606Z-0`
- Base accepted HEAD: `9f3a6190507c2ea8ee290883ee3ce143ab18c8c9`
- Upstream source truth:
  - `/home/claude/port-libs/.upstream-cache/libsqlite/test/date.test`
  - `/home/claude/port-libs/.upstream-cache/libsqlite/test/affinity2.test`
- Ported upstream sections:
  - `date.test` `date-3.18.*`: invalid `strftime('%x', ...)` conversions return `NULL`.
  - `affinity2.test` `affinity2-110..300`: inserted rows retain INTEGER, REAL, BLOB/NONE, NUMERIC, and TEXT affinity storage classes.
- Focused PHP coverage:
  - `SQLiteRealUpstreamCorpusDateAffinityDynamicInvalidStrftime20260531T032606ZTest.php`
  - `2629` TestRunner PASS cases.
  - `15768` behavior assertions.
- Non-overlap:
  - Does not cover accepted `date4` rows, date floor/ceiling, fractional unixepoch milliseconds, UTC suffix/localtime, `date20` no-round, or the broad `affinity2` comparison matrix.
  - This slice crosses `date.test` invalid conversion behavior with `affinity2.test` row affinity storage only.
- Dependency closure:
  - No new support component needed.
  - Reuses `SQLiteCoreScalarFunction` date/strftime dispatch and `SQLiteRealDateAffinityDynamicCorpusPlan::affinity2InsertedRows()`.

Verification:

```text
php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusDateAffinityDynamicInvalidStrftime20260531T032606ZTest.php
No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamCorpusDateAffinityDynamicInvalidStrftime20260531T032606ZTest.php

php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusDateAffinityDynamicInvalidStrftime20260531T032606ZTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 15768 assertions, 0 failures

php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 3 assertions, 0 failures

php -r "json_decode(file_get_contents('lanes/libsqlite/lane-status.json'), true, 512, JSON_THROW_ON_ERROR); echo 'lane-status json ok'.PHP_EOL;"
lane-status json ok

git diff --check -- lanes/libsqlite
passed with no output
```
