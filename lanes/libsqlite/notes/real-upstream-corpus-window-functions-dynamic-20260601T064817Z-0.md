# real-upstream-corpus-window-functions-dynamic-20260601T064817Z-0

## Source truth

- Upstream checkout: `/home/claude/port-libs/.upstream-cache/libsqlite/test/window1.test`
- Ported sections: `do_execsql_test 28.1.2`, `28.2.2`, and `29.2`
- Behavior cluster: `string_agg`/`group_concat` window `RANGE` frames over mixed NULL, numeric, and text order keys, including `ORDER BY d DESC RANGE BETWEEN 7.0 PRECEDING AND 2.5 PRECEDING` and `PARTITION BY c` outer ordering.

## Patch

- Added `SQLiteRealUpstreamWindow1MixedRangeDynamic20260601Test.php`.
- Added exact upstream-backed expectations for sparse numeric frames, NULL/text peer frames, and partitioned descending mixed-type frames.
- Added 1000 deterministic dynamic cases that compare `SQLiteWindowFunction::aggregateOrderedRangeValues()` with an independent mixed-type RANGE-frame oracle.
- Countable focused growth: 1005 TestRunner PASS cases and 6013 assertions.

## Verification

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamWindow1MixedRangeDynamic20260601Test.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamWindow1MixedRangeDynamic20260601Test.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamWindow1MixedRangeDynamic20260601Test.php`
  - `1 test files, 6013 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamWindow1MixedRangeDynamic20260601Test.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - `2 test files, 6018 assertions, 0 failures`
- `git diff --check -- lanes/libsqlite`
  - passed with no output

## Non-overlap

This slice owns `window1.test` 28.1.2, 28.2.2, and 29.2 mixed NULL/numeric/text `RANGE` frame behavior. It avoids accepted window1 planner-sort, alias-order, regional, subquery, range-offset sum, named-count, group-concat-empty, and recent window4/windowB/windowC/windowE batches.

## Dependency closure

No new support component is needed. The batch reuses the existing `SQLiteWindowFunction` ordered RANGE-frame executor and adds coverage around its mixed-type SQL ordering behavior.
