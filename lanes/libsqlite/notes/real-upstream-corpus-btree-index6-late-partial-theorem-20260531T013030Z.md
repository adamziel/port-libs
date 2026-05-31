## real-upstream-corpus-btree-index-dynamic-20260531T013030Z-0

Source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/index6.test`
- Upstream sections: `index6-12.1` through `index6-19.2`

Behavior ported:

- Late partial-index theorem regression cases for `IN` / `NOT IN` subqueries
- NULL truthiness with filtered indexes
- `IS FALSE`, `BETWEEN`, and `IN` predicates around NULL rows
- NOCASE collation direction guard for partial-index proof
- `GLOB` self-comparison partial index with duplicate constant unique indexes
- Partial UNIQUE predicate `a>NULL`
- RIGHT JOIN no-match-loop guard that must not full-scan a partial index

Focused PHP coverage:

- `SQLiteBTreeIndexDynamicCorpusPlan::index6LatePartialIndexTheoremCases(1000)`
- `lanes/libsqlite/tests/SQLiteRealUpstreamBtreeIndex6LatePartialTheoremDynamicTest.php`
- Expected focused PASS-line growth: `+1003` real TestRunner cases

Non-overlap:

- This does not repeat accepted `index6-7.0` through `index6-11.2` partial join/update coverage.
- This does not repeat indexA, index7, index8, index5 write-order, autoindex, catalog lifecycle, or accepted B-tree page relocation/overflow/freeblock slices.

Dependency closure:

- No new support component needed; reuses lane-local B-tree/index partial-index theorem, NULL truthiness, collation, GLOB, RIGHT JOIN, and result-row corpus helpers.
