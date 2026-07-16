# Real Upstream Corpus: B-tree Index Dynamic

Slice: `real-upstream-corpus-btree-index-dynamic-20260530T163253Z-0`

Base accepted HEAD: `92b65fe2933444167e639234f5a0c525e1097aec`

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/index.test`
- Scenarios ported: `index-10.2` through `index-10.8` duplicate-key index delete behavior, and `index-14.1` through `index-14.11` mixed NULL/integer/text index ordering and boundary predicates.

Patch summary:

- Added `SQLiteRealUpstreamBtreeIndexDynamicCorpusTest.php`.
- Uses generic application-style index records; no domain-specific APIs or fixture names were added.
- Exercises `SQLiteIndexLeafPage`, `SQLiteIndexCell`, `SQLiteBTreePageHeader`, `SQLiteBTreeFreeblock`, `SQLiteRecord`, and `SQLiteAffinityComparison`.
- Covers duplicate index-key deletion, alternating/range duplicate deletion, deletion-produced freeblock accounting, freeblock reuse for same-size index records, mixed storage-class index ordering, and boundary scans over NULL, integer, and text keys.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamBtreeIndexDynamicCorpusTest.php`
- Result: `1 test files, 762 assertions, 0 failures`
- PASS-line growth: 88 focused PASS cases.
- Expected `phpPass`: `192937 -> 193025`.
- Mapped denominator: unchanged at `958 / 1589`; this handoff ports behavior assertions and does not claim new manifest rows.

Non-overlap:

- Does not repeat accepted root collapse, overflow freelist release, index-interior merge, page relocation, freeblock-only bulk overflow release, JSON, WAL, VFS, grouped SELECT, or expression ORDER BY clusters.
- This batch is specifically upstream `index.test` dynamic duplicate-key and mixed-type index behavior.

Dependency closure:

- No new support component is needed. The existing native PHP B-tree index leaf, record, and affinity comparison components are reused directly.
