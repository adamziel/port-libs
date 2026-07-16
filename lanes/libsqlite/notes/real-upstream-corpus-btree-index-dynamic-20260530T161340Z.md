# Real Upstream Corpus: B-tree Index Dynamic Partial Index

- Slice: `real-upstream-corpus-btree-index-dynamic-20260530T161340Z-0`
- Base accepted HEAD: `8bf0d9f81b29a5601901bb34dfd730670ed39bbc`
- Upstream source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/index6.test`
- Upstream scenarios covered: `index6-1.1`, `index6-1.1.1`, `index6-1.10`, `index6-1.11`, `index6-1.12`, `index6-1.13`, `index6-1.15`, `index6-2.1`, `index6-2.2`, `index6-2.4`, `index6-2.102`, `index6-2.103`, `index6-2.104`, `index6-3.1`, `index6-3.2`, `index6-3.3`, `index6-3.4`, `index6-5.0`, `index6-9.1`, `index6-10.1`, `index6-10.2`, `index6-10.3`, `index6-11.1`, `index6-11.2`, `index6-13.1`, and `index6-14.1`.

Behavior delta:

- `SQLiteCreateIndex` now parses SQLite's upstream shorthand partial-index predicate form `WHERE c0 NOT NULL` as `IS NOT NULL`.
- New focused PHP corpus coverage exercises partial-index row admission, dynamic stat-count shifts after update/delete, unique partial-index duplicate exclusion/admission, qualified `BETWEEN` predicate parsing, `IN (...)` predicate implication, and `AND` predicate implication.
- Focused count: `1268` real TestRunner PASS lines, `1290` assertions, `0` failures.

Non-overlap:

- This does not add metadata-only suite rows, generated fake script ids, numbered production helpers, WordPress-specific APIs, B-tree page-move/freeblock/freelist duplication, WAL/VFS behavior, JSON planner behavior, or bulk suite-denominator admission.
- It is focused behavior growth against real hydrated upstream `index6.test` btree/index dynamic partial-index cases.

Dependency closure:

- No new support component is needed. The slice reuses native `SQLiteCreateIndex` parsing and `SQLiteIndexPredicate` implication helpers.

Verification:

- `php -l lanes/libsqlite/src/SQLiteCreateIndex.php` passed.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamBtreeIndexDynamicCorpusTest.php` passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamBtreeIndexDynamicCorpusTest.php` passed: `1 test files, 1290 assertions, 0 failures`, `1268` PASS lines.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoWordPressSpecificApiTest.php` was not runnable because the guard file is absent in this worktree.
