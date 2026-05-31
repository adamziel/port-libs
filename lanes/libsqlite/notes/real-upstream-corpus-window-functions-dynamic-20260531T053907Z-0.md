# real-upstream-corpus-window-functions-dynamic-20260531T053907Z-0

- Slice: `real-upstream-corpus-window-functions-dynamic-20260531T053907Z-0`
- Base accepted HEAD: `4492e9529d6540daf2941a27323f36260b8cf64c`
- Upstream source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/windowE.test`
- Ported scenarios:
  - `windowE.test` `1.2`: custom text collation RANGE frame over the existing indexed order returns singleton peer frames.
  - `windowE.test` `1.3`: after the custom collation is changed, the same indexed scan expands frames according to the runtime comparator.
- Implementation delta: added `SQLiteWindowFunction::groupConcatIndexedCustomRangeValues()` to model the bounded index-scan/custom-collation RANGE behavior needed by the upstream `windowE` regression.
- Focused PHP coverage: `SQLiteRealUpstreamWindowECustomCollationRangeDynamicTest.php` adds 1,004 focused TestRunner PASS cases and 3,005 assertions from the real upstream `windowE.test` custom-collation section, including 1,000 deterministic dynamic cases over varied labels, row counts, separators, and comparator direction.
- Non-overlap: this owns the previously excluded `windowE.test` `1.2`/`1.3` custom-collation RANGE/index-order behavior. It avoids accepted `windowE` numeric RANGE/sum/total frames, `windowA` NULL placement, `windowB` JSON/object inverse and mixed RANGE rows, `window5` custom aggregate coverage, `window9` collation/filter rows, window pushdown/error/fault batches, JSON table/source/constraint work, WAL/VFS/B-tree/planner/PRAGMA/trigger clusters, and metadata-only runner rows.
- Dependency closure: no new support component is needed; this reuses the native `SQLiteWindowFunction` window helper surface with a bounded runtime comparator.

Verification:

```text
php -l lanes/libsqlite/src/SQLiteWindowFunction.php
No syntax errors detected in lanes/libsqlite/src/SQLiteWindowFunction.php

php -l lanes/libsqlite/tests/SQLiteRealUpstreamWindowECustomCollationRangeDynamicTest.php
No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamWindowECustomCollationRangeDynamicTest.php

php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamWindowECustomCollationRangeDynamicTest.php
1 test files, 3005 assertions, 0 failures

php tools/run-tests.php lanes/libsqlite/tests/SQLiteWindowRangeNullsLargeCorpusTest.php lanes/libsqlite/tests/SQLiteRealUpstreamWindowECustomCollationRangeDynamicTest.php
2 test files, 9400 assertions, 0 failures

php -r 'json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, flags: JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'
lane-status json ok

git diff --check -- lanes/libsqlite
passed
```
