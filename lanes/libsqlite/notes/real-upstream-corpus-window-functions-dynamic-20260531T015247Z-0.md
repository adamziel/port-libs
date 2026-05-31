# real-upstream-corpus-window-functions-dynamic-20260531T015247Z-0

- Upstream source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/windowB.test`.
- Ported scenario IDs:
  - `windowB.test 3.11`: `json_group_object(if(id>4,k||'@'),v)` with sliding `ROWS BETWEEN 1 PRECEDING AND 1 FOLLOWING`.
  - `windowB.test 3.12`: all keys contribute to `json_group_object()` while inverse frame removal slides forward.
  - `windowB.test 3.13`: first and last source rows contribute NULL keys while middle keys survive inverse removal.
  - `windowB.test 3.15`: only outside edge labels contribute to the sliding JSON object frame.
  - `windowB.test 5.1-5.5` and `6.1-6.2`: reversed `RANGE` frame boundaries keep NULL/text peer groups while numeric rows produce empty frames.
- Focused coverage: `1009` distinct TestRunner PASS cases, `3009` assertions.
- Non-overlap: this fills the `windowB.test` inverse JSON-object and reversed RANGE remainder not covered by the accepted `windowB` rows for `3.9`, `3.10`, `3.14`, `3.16`, `7.1-7.4`, `9.0`, `10.2-10.3`, and `11.3-11.10`. It adds no metadata-only runner rows, fake upstream script IDs, generated suite rows, WordPress-specific API names, or storage/VFS/B-tree behavior.
- Dependency closure: no new support component is needed; the test reuses existing native `SQLiteJsonAggregate` and `SQLiteWindowFunction` behavior.

Verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamWindowBInverseRangeRemainderDynamicTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 3009 assertions, 0 failures
```
