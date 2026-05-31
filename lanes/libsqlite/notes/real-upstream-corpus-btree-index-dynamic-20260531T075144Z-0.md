# real-upstream-corpus-btree-index-dynamic-20260531T075144Z-0

- Base accepted HEAD: `9d7a6158784515939dbe96138a460121fe325c71`.
- Upstream source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/bestindexC.test`, sections `bestindexC-4.0`, `bestindexC-4.2`, `bestindexC-4.3`, and `bestindexC-4.4`.
- Added focused PHP corpus: `SQLiteBTreeIndexDynamicCorpusPlan::bestindexCVirtualTableDeclarationErrorCases(1000)` and `SQLiteRealUpstreamBestIndexCDeclarationErrorDynamicTest.php`.
- Focused assertion/PASS growth: 1003 distinct TestRunner PASS cases from real upstream `bestindexC.test` declaration-error behavior.
- Non-overlap: adjacent accepted files already cover `bestindexC` LIMIT/OFFSET sections `1.2` through `3.6` and constraint/RHS sections `5.2` through `6.6`; this slice owns only `xConnect` / `declare_vtab` error preservation from section 4.
- Dependency closure: no new support component needed; the slice reuses the lane-local B-tree/index dynamic corpus planner and existing virtual-table diagnostic modeling.
