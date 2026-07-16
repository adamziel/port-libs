# Real Upstream B-tree Index Dynamic Slice

- Slice: `real-upstream-corpus-btree-index-dynamic-20260530T211232Z-0`
- Accepted base: `bbccc1d8f736962c4f86ebb79411aec5c77c5f5a`
- Upstream source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/index8.test`
- Upstream sections: `index8-1.0`, `index8-1.0eqp`, `index8-1.1`, and `index8-1.1eqp`
- Added focused coverage: `1000` dynamic ORDER BY/LIMIT planner cases plus one corpus count case.
- Focused result: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamBtreeIndex8OrderLimitDynamicTest.php` passed with `1 test files, 34893 assertions, 0 failures`.
- Non-overlap: extends the accepted `index8` ORDER BY/LIMIT planner regression across all `c` residues and bounded LIMIT values. It does not touch accepted page relocation, overflow freelist, root collapse, grouped SELECT, JSON table, WAL, VFS writer, or domain-specific smoke surfaces.
- Dependency closure: no new support component needed; reuses the existing lane-local B-tree/index dynamic corpus plan helper.
