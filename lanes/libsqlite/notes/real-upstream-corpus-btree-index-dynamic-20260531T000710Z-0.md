# Real Upstream Corpus: B-tree / Index Late Lifecycle

- Upstream source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/index.test`.
- Upstream scenarios ported: `index-10.0` through `index-23.1`, covering duplicate non-unique index keys, primary-key autoindex lookup, NUMERIC affinity indexed range probes, autoindex drop rejection, mixed NULL/numeric/text index sort order, exponent string numeric conversion, redundant UNIQUE/PRIMARY KEY autoindex generation, reserved `sqlite_` names, merged conflict policy, quoted and TEMP index namespace behavior, expression index `IF NOT EXISTS`, and expression-index `REINDEX` regressions.
- Focused addition: `SQLiteBTreeIndexDynamicCorpusPlan::indexLateLifecycleAndAffinityCases(1000)` plus `SQLiteRealUpstreamBtreeIndexLateLifecycleDynamicTest.php`.
- Focused TestRunner PASS growth: 1003 distinct focused PASS cases in the new test file.
- Non-overlap: this extends `index.test` sections after the existing catalog-lifecycle `index-1.1` through `index-9.2` batch. It does not repeat accepted B-tree page relocation, overflow freelist/freeblock release, root collapse, index-interior merge, index2 wide-column, index3 quoted identifier, index4/index5 create-index, index7 partial-index, index8 order/limit, index9 bound partial-index, indexA planner/affinity, autoindex1/3/4/5, indexedby, indexfault, numindex1, or indexexpr JSON covering batches.
- Dependency closure: no new support component is needed; the slice reuses the lane-local B-tree/index dynamic corpus generator and focused TestRunner harness.

Verification:

- `php -l lanes/libsqlite/src/SQLiteBTreeIndexDynamicCorpusPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamBtreeIndexLateLifecycleDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamBtreeIndexLateLifecycleDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
- `git diff --check -- lanes/libsqlite`
