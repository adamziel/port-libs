# Real Upstream Corpus Window Functions Dynamic

- Slice: `real-upstream-corpus-window-functions-dynamic-20260530T162729Z-0`
- Base accepted HEAD: `72e7cdb1ae891bd4c5cdf5658524a5a35974f525`
- Upstream source truth:
  - `/home/claude/port-libs/.upstream-cache/libsqlite/test/window2.test`
  - `/home/claude/port-libs/.upstream-cache/libsqlite/test/window3.test`
- Ported scenarios:
  - `window2.test` subtests `2.1`, `2.2`, `2.3`, `2.4`, `2.5`, `2.8`, `2.9`, `2.11`, `2.13`, `2.14`, `2.17`, `2.20`, `2.21`, `2.23`, `2.25`, and `2.29`.
  - Generated `window3.test` RANGE/GROUPS peer frame scenarios for `CURRENT ROW`, `PRECEDING`, `FOLLOWING`, empty start-after-end frames, and `EXCLUDE GROUP`.
- Behavior change:
  - Added `SQLiteWindowFunction::aggregateFrameBetweenValues()` so the port can model SQL frame boundaries directly instead of only symmetric preceding/following offsets.
  - Fixed ROWS frames whose bounded start/end fall outside the partition so `ROWS BETWEEN 3 PRECEDING AND 1 PRECEDING` on the first row and `ROWS BETWEEN 1 FOLLOWING AND 2 FOLLOWING` on the last row produce empty frames, matching upstream `window2.test`.
- Focused assertion count:
  - `SQLiteRealUpstreamWindowFrameDynamicCorpusTest.php`: `661 assertions / 0 failures`.
- Non-overlap:
  - This is not the accepted RANGE/GROUPS no-ORDER guard, named-window subquery, JSON aggregate window, row-value returning window, or metadata/admission suite work. It ports real upstream frame-boundary result behavior from hydrated SQLite `.test` files into PHP assertions.
- Dependency closure:
  - No new support component is needed. The slice reuses the existing native PHP `SQLiteWindowFunction` helper and extends its SQL frame-boundary model.
