# Real Upstream Corpus Expression Affinity Dynamic

Base accepted HEAD: `e35ca6042fb93bdd5d8709bbc17efa06e6d9c2b0`

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/hexlit.test`
  - `hexlit-300`: string literals that look like hex do not get cast or coerced by column affinity.
  - `hexlit-301`: `CAST('0x1234' AS INTEGER)` returns `0`.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/literal.test`
  - `1.11` through `1.13`: quoted hex-looking text remains text; unary numeric coercion returns zero.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/types2.test`
  - Additional `types2-1.*` equality rows for literal, TEXT-affinity, NUMERIC-affinity, and no-affinity comparison behavior.

Implemented behavior:

- `SQLiteRealExpressionAffinityCorpusPlan::applyInsertAffinities()` now distinguishes column affinity from explicit `CAST`.
- INTEGER and REAL column affinity first attempts NUMERIC affinity and preserves non-convertible text/blob values such as `0x1234` and `-0xFF`.
- Explicit `CAST(... AS INTEGER|NUMERIC)` still follows SQLite CAST prefix behavior and returns numeric zero for hex-looking quoted text.

Focused evidence:

- Before this follow-up, `SQLiteRealUpstreamCorpusExpressionAffinityDynamicTest.php` passed with `5557` assertions.
- After this follow-up, the same focused file passes with `5737` assertions.
- Added `28` distinct TestRunner PASS cases and `180` focused assertions.

Dependency closure:

- No new support component is needed. The slice reuses the existing native expression-affinity corpus helper and narrows its insert-affinity behavior to match upstream SQLite.

Non-overlap:

- This does not add metadata-only denominator rows and does not claim mapped coverage movement.
- It is distinct from already accepted expression ORDER BY, range-cost, Unicode GLOB, and general CAST corpus coverage by targeting upstream hex-looking text column-affinity preservation and additional `types2.test` equality rows.
