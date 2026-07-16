# real-upstream-corpus-btree-index-dynamic-20260531T060554Z-0

- Base accepted HEAD: `5a0bbcc53e4d53b976a73e07fed57fd92e934f80`.
- Upstream source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/indexfault.test`.
- Ported sections: `indexfault-4.1`, `indexfault-4.2`, and `indexfault-5`.
- Focused behavior: temp B-tree readback after `sqlite3_release_memory()` during `CREATE INDEX`, low soft-heap-limit readback fault handling, and very long `WITHOUT ROWID` primary-key table-name schema B-tree admission.
- Added focused PHP cases: `1000` distinct TestRunner PASS cases plus 3 guard/dependency cases in `SQLiteRealUpstreamBtreeIndexFaultTempReadbackDynamicTest.php`.
- Non-overlap: does not repeat accepted indexfault create-index fault templates, B-tree page moves, overflow freelist release, index-interior merge, or SQL/JSON/WAL/VFS accepted clusters.
- Dependency closure: no new support component needed; this reuses the lane-local B-tree/index dynamic corpus planner and existing fault/readback/schema-path fixtures.
- Root harness: not run - isolated micro-slice.
