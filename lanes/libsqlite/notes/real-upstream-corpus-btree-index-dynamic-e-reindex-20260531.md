# real-upstream-corpus-btree-index-dynamic e_reindex

- Base accepted HEAD: `598504695c988ec41a0063207004e700089f5af7`.
- Upstream source: `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_reindex.test`.
- Owned upstream sections: `e_reindex-0.1.1`, `e_reindex-0.1.2`, `e_reindex-1.3/1.4`, `e_reindex-2.2.1/2.7`, `e_reindex-2.3.1/3.7`, `e_reindex-2.3.8/3.14`, `e_reindex-2.4.1/4.7`, `e_reindex-2.4.8/4.14`, `e_reindex-2.4.15/4.21`, and `e_reindex-2.5.1/5.7` through `e_reindex-2.5.29/5.34`.
- Behavior added: lane-local B-tree/index dynamic corpus planner rows for REINDEX syntax admission, corrupt-index repair, and collation/table/index scoped rebuild behavior across main and attached schemas.
- Focused TestRunner movement: `+1003` PASS lines, `19013` assertions, `0` failures.
- Non-overlap: this owns `e_reindex.test`, which was not represented in the current B-tree/index dynamic corpus scan. It does not repeat accepted `reindex.test`, `indexA`, `index7`, `index6`, index page-move, overflow freelist, or visible/hidden JSON constraint clusters.
- Dependency closure: no new support component needed; this reuses the existing lane-local B-tree/index dynamic corpus planner plus collation-order, schema-scope, corrupt-index repair, and integrity diagnostics.
- Domain note: generic SQLite coverage only; no domain-specific API or fixture naming added.

Verification:

- `php -l lanes/libsqlite/src/SQLiteBTreeIndexDynamicCorpusPlan.php` passed.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamBtreeEReindexDynamicTest.php` passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamBtreeEReindexDynamicTest.php` passed: `1 test files, 19013 assertions, 0 failures`.
