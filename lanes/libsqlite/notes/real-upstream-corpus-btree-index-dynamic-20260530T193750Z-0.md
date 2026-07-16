# Real Upstream Corpus: B-tree / Index Dynamic index5 Write Order

- Slice: `real-upstream-corpus-btree-index-dynamic-20260530T193750Z-0`
- Base accepted HEAD: `28f29f1b7137ae1bf099a6bea9838aec79fed0b3`
- Upstream source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/index5.test`
- Upstream scenarios covered: `index5-1.1` through `index5-1.3`, including the 1024-byte page-size setup, 100000-row table population, VFS `xWrite` capture during `CREATE INDEX i1 ON t1(x)`, and the final assertion that forward page writes dominate backward and non-contiguous writes.

Behavior delta:

- Wired the existing `SQLiteBTreeIndexDynamicCorpusPlan::index5SequentialWriteCases()` real upstream generator into `SQLiteRealUpstreamBTreeIndexDynamicCorpusTest.php`.
- Adds 1200 distinct focused TestRunner PASS cases and roughly 15600 behavior assertions for the upstream `index5.test` write-order transition corpus.
- The focused file now verifies 1876 PASS lines and 38993 assertions with zero failures.

Non-overlap:

- This does not add metadata-only runner rows, generated fake `.test` script ids, mapped-denominator claims, source-neutral cleanup, WordPress-specific APIs, B-tree page-move/freeblock/freelist duplication, WAL/VFS behavior, JSON planner behavior, or suite evidence rows.
- The new surface is specifically upstream `index5.test` CREATE INDEX write-order behavior, not the already accepted `indexA`, `index6`, `index9`, `indexedby`, B-tree page relocation, root-collapse, overflow freelist, expression-index range-cost, or VFS writer clusters.

Dependency closure:

- No new support component is needed. The slice reuses existing native B-tree/index corpus planning and focused PHP TestRunner infrastructure.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamBTreeIndexDynamicCorpusTest.php` passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamBTreeIndexDynamicCorpusTest.php` passed: `1 test files, 38993 assertions, 0 failures`, `1876` PASS lines.
