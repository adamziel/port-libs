# real-upstream-corpus-btree-index-dynamic-20260531T014317Z-0

Base accepted HEAD: `d0e37b664c0ef9500748faeafd4d7f1484470255`

This slice adds a non-overlapping B-tree/index dynamic corpus batch from the hydrated SQLite upstream checkout:

- Upstream source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/where8.test`.
- Upstream sections covered: `where8-1.2`, `where8-1.3`, `where8-1.9`, `where8-1.11`, `where8-1.12.1`, `where8-1.13`, and `where8-1.15`.
- Behavior ported: ordinary B-tree index OR optimization over two real indexes, including index rowid-union probes, range arms, BETWEEN arms, ordered rowid output, duplicate-safe rowid union metadata, and same-column OR-to-IN rewrite.
- PHP focused growth: 1203 focused PASS lines and 24178 assertions in `SQLiteRealUpstreamBtreeWhere8OrDynamicTest.php`.

Files changed:

- `lanes/libsqlite/src/SQLiteBTreeIndexDynamicCorpusPlan.php`
- `lanes/libsqlite/tests/SQLiteRealUpstreamBtreeWhere8OrDynamicTest.php`
- `lanes/libsqlite/lane-status.json`
- `lanes/libsqlite/notes/real-upstream-corpus-btree-index-dynamic-20260531T014317Z-0.md`

Focused verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamBtreeWhere8OrDynamicTest.php`
  - Result: `1 test files, 24178 assertions, 0 failures`
  - PASS lines: 1203

Non-overlap:

This does not repeat accepted B-tree page relocation, index-interior merge, root collapse, overflow freelist release, bulk overflow freeblocks, `index6` partial-index batches, `index7` partial unique/planner batches, `index8` ORDER BY/LIMIT planner batches, `indexA` partial affinity batches, `autoindex1` automatic index batches, bestindex virtual-table batches, expression-index cost/JSON-covering batches, or metadata-only runner rows. The owned gap is upstream `where8.test` ordinary table OR optimization through existing `SQLiteOrOptimizationPlan`.

Dependency closure:

No new support component is needed. The slice reuses the lane-local B-tree/index dynamic corpus planner and existing `SQLiteOrOptimizationPlan` index-union and OR-to-IN behavior.

Root harness:

Not run - isolated micro-slice.
