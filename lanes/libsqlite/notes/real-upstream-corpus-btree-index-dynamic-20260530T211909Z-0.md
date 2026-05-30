# real-upstream-corpus-btree-index-dynamic-20260530T211909Z-0

Base accepted HEAD: `79fe7adeaeaffcf972bbb3cc5bff694c367cc63d`.

This slice adds a non-overlapping B-tree/index automatic partial-index corpus batch from the hydrated SQLite upstream checkout:

- Upstream source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/autoindex4.test`.
- Upstream sections covered: `autoindex4-1.0` through `autoindex4-4.8`.
- Focus: automatic partial-index behavior around constant filters, ON-vs-WHERE filtering, LEFT/RIGHT JOIN equivalence, scalar subquery counts, ORDER BY preservation, declared partial-index parity, NULL-preserving outer joins, empty `NOT IN`, and optimization-control parity.
- New focused TestRunner PASS cases: `1001`.
- Focused behavior assertions: `12422`.
- Non-overlap: avoids accepted `autoindex1`, `autoindex2`, and `autoindex5` batches, plus accepted B-tree page relocation, overflow freelist release, bulk overflow freeblocks, index-interior merge, index expression, indexfault, index5 write-order, indexA join planner, and numeric-affinity batches already present in the accepted B-tree/index corpus.

Verification:

- `php -l lanes/libsqlite/src/SQLiteAutoIndexDynamicPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamAutoIndexPartialJoinDynamicCorpusTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamAutoIndexPartialJoinDynamicCorpusTest.php`
  - `1 test files, 12422 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - `1 test files, 3 assertions, 0 failures`
- `git diff --check -- lanes/libsqlite`
  - passed with no output

Dependency closure: no new support component is required. The batch reuses existing lane-local dynamic corpus planning and the focused PHP TestRunner.

Root harness: not run - isolated micro-slice.
