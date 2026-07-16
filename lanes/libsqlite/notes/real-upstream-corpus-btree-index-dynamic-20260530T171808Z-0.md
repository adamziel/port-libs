# real-upstream-corpus-btree-index-dynamic-20260530T171808Z-0

- Base accepted HEAD for this isolated worker: `7ae2bafb13ace2a8edf7ffe53e4f4d55f2e4902f`.
- Added focused PHP coverage for the existing generic `SQLiteBTreeIndexDynamicCorpusPlan` behavior cluster.
- Upstream source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/btree01.test`, `btree02.test`, `index.test`, `index6.test`, `index9.test`, and `indexedby.test`.
- Covered upstream scenarios: `btree01-1.2.*` through `btree01-1.8.*` dynamic overflow balance rows, `index-4.2` through `index-4.12` create/drop index lookup lifecycle, `index9-1.1` through `index9-4.5` bound partial-index proofs, `indexedby-11.2` through `indexedby-11.9` rowid affinity through covering indexes, `btree02-110.*` cursor mutation during scan, and `index6-7.0` through `index6-11.2` partial-index join/update cases.
- Focused assertion growth: the new test file contributes `270` TestRunner PASS cases and `2428` assertions.
- Non-overlap: this is not metadata-only admission and does not claim new denominator rows; it exercises a distinct current-base B-tree/index dynamic corpus helper and avoids accepted page relocation, overflow freelist release, visible JSON constraints, WAL/VFS commit paths, SELECT text, and no-domain cleanup.
- Dependency closure: no new support component needed; the test reuses lane-local B-tree/index page, record, planner, and cursor-case helpers.
