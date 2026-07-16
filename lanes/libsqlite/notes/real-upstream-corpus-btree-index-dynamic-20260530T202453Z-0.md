## real-upstream-corpus-btree-index-dynamic-20260530T202453Z-0

Source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/index7.test`.

Ported upstream sections:

- `index7-1.1` through `index7-1.15`: WITHOUT ROWID partial-index membership and stat1 count transitions.
- `index7-2.1` through `index7-2.104`: WITHOUT ROWID null-rejecting and range-union partial-index row membership.
- `index7-3.1` through `index7-3.5`: partial UNIQUE index conflict and allowed duplicates outside the partial predicate.
- `index7-5.0`: database-qualified partial-index predicate count behavior.

Focused assertion growth: the new `SQLiteRealUpstreamCorpusBTreeIndexWithoutRowidPartialDynamicTest.php` file adds 2,028 distinct TestRunner cases from real upstream row and section behavior. It is non-overlapping with the existing accepted B-tree/index dynamic corpus because it targets `index7.test` WITHOUT ROWID partial-index behavior rather than the existing `index6`, `index9`, `indexA`, `index2`, `index4`, `index8`, `indexedby`, `btree01`, or `btree02` sections.

Dependency closure: no new support component is needed; this reuses the existing B-tree record/index-page and real-upstream corpus helper structure.
