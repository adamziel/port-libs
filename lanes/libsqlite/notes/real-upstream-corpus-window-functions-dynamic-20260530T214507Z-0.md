# Real Upstream Window5 Custom Aggregate Dynamic Batch

- Slice: `real-upstream-corpus-window-functions-dynamic-20260530T214507Z-0`
- Accepted base: `551608c47b9b5c9b4c74afdd6349b99f03720fcd`
- Upstream source truth:
  - `/home/claude/port-libs/.upstream-cache/libsqlite/test/window5.test`
- Ported upstream scenarios:
  - `window5.test` `1.1`: custom `win(a)` sorted-frame value output and custom `median(a)` window aggregate over `ORDER BY b`.
  - `window5.test` `2.0` and `2.1`: custom `sumint(a)` running frame and inverse-style sliding row frame behavior.
  - `window5.test` `3.0` and `3.1`: overridden aggregate rejection as a window function while ordinary aggregate summation remains valid.
- Implementation:
  - Added generic `SQLiteWindowFunction::customFrameBetweenValues()` for callback-backed window frames.
  - Added `medianFrameBetweenValues()` and `sortedFrameTextValues()` as generic custom aggregate helpers.
- Focused coverage:
  - Added `SQLiteRealUpstreamWindow5CustomAggregateDynamicTest.php`.
  - Focused run produced `1 test files, 3007 assertions, 0 failures`, with 1006 distinct TestRunner PASS cases.
- Non-overlap:
  - This owns upstream `window5.test` custom window aggregate behavior. It does not repeat accepted `window1`/`window2` frame matrices, `window3`/`window4` ranking and value batches, `window6` value-function argument handling, `window7`/`window8` GROUPS/RANGE batches, `window9` filtered min/collation coverage, `windowA`/`windowB`/`windowC`/`windowD`/`windowE` dynamic batches, or metadata-only runner admission rows.
- Dependency closure:
  - No new support component is needed; this reuses lane-local window frame parsing/exclusion/filter semantics in `SQLiteWindowFunction`.

Verification:

```text
php -l lanes/libsqlite/src/SQLiteWindowFunction.php
No syntax errors detected in lanes/libsqlite/src/SQLiteWindowFunction.php

php -l lanes/libsqlite/tests/SQLiteRealUpstreamWindow5CustomAggregateDynamicTest.php
No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamWindow5CustomAggregateDynamicTest.php

php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamWindow5CustomAggregateDynamicTest.php
1 test files, 3007 assertions, 0 failures

php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamWindow5CustomAggregateDynamicTest.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php
2 test files, 3010 assertions, 0 failures

php -r 'json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'
lane-status json ok

git diff --check -- lanes/libsqlite
no output
```
