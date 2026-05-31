# real-upstream-corpus-btree-index-dynamic-20260530T235328Z-0

Slice: `real-upstream-corpus-btree-index-dynamic-20260530T235328Z-0`

Added a focused real-upstream B-tree/index dynamic corpus batch for `autoindex3.test`.

- Upstream source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/autoindex3.test`.
- Upstream sections covered: `autoindex3-100`, `autoindex3-110`, `autoindex3-120`, `autoindex3-130`, `autoindex3-140`, `autoindex3-220`, and `autoindex3-310`.
- Focus: declared equality indexes suppress automatic indexes that would only shadow them; non-equality declared-index constraints still allow transient covering indexes on independent equality predicates; skip-scan is not preferred over a transient automatic covering index when STAT1 selectivity says otherwise; recursive CTE seed and recursive steps continue using the declared `(pid, rx)` index instead of a low-selectivity automatic index.
- Focused assertion growth: `SQLiteRealUpstreamBtreeAutoindex3DynamicTest.php` adds 1003 distinct TestRunner PASS cases and passed with 15631 assertions.
- Non-overlap: avoids accepted `autoindex1`, `autoindex2`, `autoindex4`, `autoindex5`, index7/index8/index9/indexA/indexfault/indexexpr/index lifecycle batches, B-tree page relocation/root collapse/overflow freelist/freeblock clusters, VFS/WAL storage clusters, and metadata-only runner rows.

Verification:

- `php -l lanes/libsqlite/src/SQLiteBTreeIndexDynamicCorpusPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamBtreeAutoindex3DynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamBtreeAutoindex3DynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
- `git diff --check -- lanes/libsqlite`

Dependency closure: no new support component is needed. This reuses the existing bounded B-tree/index dynamic corpus planner pattern and ports a non-overlapping upstream planner behavior cluster into focused PHP tests.
