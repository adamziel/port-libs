# real-upstream-corpus-window-functions-dynamic-20260531T000204Z-0

- Upstream source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/windowfault.test`.
- Ported disjoint upstream sections:
  - `windowfault.test:11.1` tuple `IN` with `DENSE_RANK() OVER()` and `LAG(0) OVER()` returning no rows.
  - `windowfault.test:11.2` `VALUES ... INTERSECT ... count() ... group_concat(...) OVER()` returning no rows.
  - `windowfault.test:12.0-12` empty CTE with `row_number() OVER (PARTITION BY a COLLATE nocase ORDER BY b)` filtered by `a=2`.
- Focused assertion/pass delta: `SQLiteRealUpstreamWindowFaultTailDynamicTest.php` adds 1004 focused TestRunner cases and 6004 behavior assertions from real upstream windowfault tail semantics.
- Non-overlap: avoids existing accepted windowfault coverage for sections 1-10 and 13, plus existing `window1` through `windowE`, `windowerr`, and `windowpushd` dynamic batches. This patch adds no metadata-only runner rows, generated fake upstream script IDs, domain-specific APIs, or JSON/WAL/B-tree/VFS behavior.
- Dependency closure: no new support component is needed; this reuses native `SQLiteWindowFunction` ranking/value/aggregate helpers and `SQLiteSelectPredicate` comparison wiring.
