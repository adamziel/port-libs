# Real Upstream Corpus B-tree Index Dynamic

Slice: `real-upstream-corpus-btree-index-dynamic-20260530T201048Z-0`

Accepted base: `c1a0d2c80ea721e0595b20a5cbe43c5043856066`

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/index2.test`
- Scenarios: `index2-1.1` through `index2-1.5`, `index2-2.1`, `index2-2.2`

Coverage added:

- `SQLiteRealUpstreamCorpusBTreeIndex2WideColumnDynamicTest.php`
- 2,012 distinct TestRunner PASS cases.
- 13,055 focused assertions.
- Exercises the upstream 1000-column table materialization, exact `c1000` sum, one single-column index leaf per upstream column, and multi-column prefix seek order for selected upstream rows.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusBTreeIndex2WideColumnDynamicTest.php`
  - `No syntax errors detected`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusBTreeIndex2WideColumnDynamicTest.php`
  - `1 test files, 13055 assertions, 0 failures`

Non-overlap:

- Builds on the existing accepted `index2.test` wide-row helper but covers every upstream column rather than the already accepted sampled wide-index/order-by cases.
- Does not add domain-specific API, classes, methods, or source defaults.

Dependency closure:

- No new support component is needed. The slice reuses existing native PHP record, index-cell, and index-leaf page helpers.
