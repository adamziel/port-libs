# real-upstream-corpus-window4-dynamic-20260531T031416Z

Slice: `real-upstream-corpus-window-functions-dynamic-20260531T031416Z-0`

Accepted base: `d3f35d53d135e23f73a270582d60d9916715bb54`.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/window4.test` sections `1.1-1.19`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/window4.test` sections `2.1-2.3.3`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/window4.test` sections `3.1-3.6.3`

Ported behavior:

- `ntile()` bucket distribution when bucket count is smaller, equal, and larger than row count.
- `lead()` and `lag()` offset/default handling.
- Per-row `nth_value()` indexes over bounded ROWS frames.
- 1000 dynamic cases combine row counts, bucket counts, offsets, frame bounds, and per-row nth indexes.

Focused growth:

- New test file: `lanes/libsqlite/tests/SQLiteRealUpstreamCorpusWindow4DynamicValueTest.php`.
- Expected focused TestRunner growth: `1005` PASS cases from real upstream window behavior.
- Focused behavior assertions: `4011`.

Non-overlap:

- Does not repeat accepted `window9` collation/filter frames, `windowA` dynamic RANGE behavior, `window4` ntile/value batch noted in batch55, JSON object inverse window behavior, parser-level grouped SELECT, or accepted JSON table window-ranking/source/cursor work.
- This slice owns a dynamic `window4.test` value-function matrix around bucket/offset/nth semantics on the current accepted base.

Dependency closure:

- No new support component is needed. The slice reuses existing native `SQLiteWindowFunction` helpers.
