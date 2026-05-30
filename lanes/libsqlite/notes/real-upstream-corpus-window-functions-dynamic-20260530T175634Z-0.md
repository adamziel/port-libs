# Real Upstream Window Functions Dynamic Batch

- Slice: `real-upstream-corpus-window-functions-dynamic-20260530T175634Z-0`
- Base accepted HEAD: `e35ca6042fb93bdd5d8709bbc17efa06e6d9c2b0`
- Upstream source truth:
  - `/home/claude/port-libs/.upstream-cache/libsqlite/test/window3.test`
  - `/home/claude/port-libs/.upstream-cache/libsqlite/test/window4.test`
- Ported behavior:
  - `window4.test` `1.1` through `1.19` dynamic `ntile()` bucket distribution across bucket counts wider than the row count.
  - `window4.test` `2.1`, `2.2.*`, `2.3.*`, `3.1`, and `3.2` dynamic `nth_value()`, `lead()`, and `lag()` row offsets, including negative offset parity.
  - `window3.test` `1.1.3`, `1.1.4`, and `1.1.5` ranking functions over the 191-row upstream corpus.
  - Additional `window3.test`/`window4.test` frame semantics for `ROWS`, `RANGE`, and `GROUPS` aggregate and value windows, including peer exclusion behavior.
- Focused assertion/PASS growth:
  - New focused test file: `lanes/libsqlite/tests/SQLiteRealUpstreamWindowFunctionsDynamicBatchTest.php`
  - Verified focused assertions / PASS cases: 2,154.
- Non-overlap:
  - This does not repeat SQL text window frames, JSON window behavior, metadata admission rows, or existing `SQLiteRealUpstreamCorpusWindowFunctionsDynamicTest.php` frame-boundary vectors. It expands helper-level dynamic offset/ranking/value-frame coverage from exact upstream `window3.test` and `window4.test` sections.
- Dependency closure:
  - No new support component needed; this reuses native `SQLiteWindowFunction` helpers and independent in-test oracle code.
