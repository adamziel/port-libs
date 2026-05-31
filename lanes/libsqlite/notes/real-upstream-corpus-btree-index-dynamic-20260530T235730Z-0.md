# real-upstream-corpus-btree-index-dynamic-20260530T235730Z-0

- Accepted base: d045774aa6bf87ca954fff751277766f57e01075.
- Upstream source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/index2.test`.
- Ported sections: `index2-1.3`, `index2-1.5`, `index2-2.1`, and `index2-2.2`.
- Behavior: 1000-column table projection, `c1000` aggregate preservation after 101 wide rows, 1000-column index construction metadata, and `ORDER BY c1..cN LIMIT N` prefix scan results.
- Focused PASS delta: +1002 TestRunner PASS cases.
- Focused assertions: 23006 assertions / 0 failures.
- Mapped denominator movement: none; mapped inventory remains 1589 / 1589.
- Non-overlap: extends the existing B-tree/index dynamic corpus with a new aggregate/projection batch; it does not repeat accepted PDO cleanup, existing wide-column order-only cases, index catalog lifecycle cases, index5 write-order cases, or indexexpr JSON covering cases.
- Dependency closure: no new support component needed; this reuses the lane-local B-tree/index dynamic corpus planner and existing TestRunner.

Verification:

```text
php -l lanes/libsqlite/src/SQLiteBTreeIndexDynamicCorpusPlan.php
No syntax errors detected in lanes/libsqlite/src/SQLiteBTreeIndexDynamicCorpusPlan.php

php -l lanes/libsqlite/tests/SQLiteRealUpstreamBtreeIndex2AggregateProjectionDynamicTest.php
No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamBtreeIndex2AggregateProjectionDynamicTest.php

php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamBtreeIndex2AggregateProjectionDynamicTest.php
1 test files, 23006 assertions, 0 failures
```
