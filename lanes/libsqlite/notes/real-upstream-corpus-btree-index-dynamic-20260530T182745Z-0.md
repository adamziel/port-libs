# Real Upstream B-tree/Index Dynamic Corpus

Slice: `real-upstream-corpus-btree-index-dynamic-20260530T182745Z-0`
Base accepted HEAD: `2b09fd94bbc734a3a9855d41884522c7a5a06914`

## Upstream Source

- Hydrated upstream file: `/home/claude/port-libs/.upstream-cache/libsqlite/test/index6.test`
- Ported scenarios: `index6-1.1` through `index6-2.104`
- Behavior covered: partial-index row membership, reduced `sqlite_stat1` row-count transitions, partial-index implication for `a IS NOT NULL`, and partial-index implication for `a<100 OR a>200`.

## Focused Evidence

- `php -l lanes/libsqlite/src/SQLiteRealUpstreamBTreeIndexDynamicCorpus.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusBTreeIndexPartialDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusBTreeIndexPartialDynamicTest.php`
  - Result: `1 test files, 8185 assertions, 0 failures`
  - PASS lines: `1030`

## Non-overlap

This handoff extends the existing real upstream B-tree/index dynamic corpus with `index6.test` partial-index behavior. It does not repeat accepted B-tree overflow/freeblock/page-move/root-collapse slices, prior `index.test` duplicate-key coverage, `index4.test` integrity coverage, `indexedby.test` planner forcing, or source-neutral cleanup.

## Dependency Closure

No new support component is needed. The slice uses existing PHP corpus helpers and deterministic generic row arrays derived from upstream SQLite Tcl scenarios. It is PASS-line growth only; mapped-denominator movement still requires guarded upstream-runner evidence.
