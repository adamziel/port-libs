## Media Query Range Layer Parity 2026-06-01T182759Z

Source truth:
- Upstream LightningCSS commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- `src/rules/media.rs` runs `MediaList::transform_resolution()` for `@media` rules before printing.
- `src/rules/import.rs` prints import media lists without running `transform_resolution()`, so import tails lower unsupported range syntax and convert resolution units, but do not clone WebKit/Mozilla device-pixel-ratio query variants.
- Direct pinned native-addon probes using `lightningcss.linux-x64-gnu.node` confirmed:
  - `@import "blocks/density.css" layer(theme.blocks) (resolution >= 2dppx);` with Safari 15 and Firefox 10 prints `(min-resolution:2dppx)`.
  - `@import "blocks/density.css" layer(theme.blocks) (resolution = 2dppx);` prints `(resolution:2dppx)`.
  - `@import "blocks/density.css" layer(theme.blocks) not (resolution >= 2dppx);` prints `not (min-resolution:2dppx)`.

Implemented behavior:
- `TransitionPrefixer` no longer clones legacy resolution-prefix variants while rewriting layered `@import` media tails.
- The same path still lowers simple and interval media range syntax and still applies target-specific `x`/`dppx` resolution unit serialization for import media lists.
- `@media` blocks are unchanged and continue to receive upstream WebKit/Mozilla resolution-prefix variants.

Focused evidence:
- `php -l lanes/lightningcss/src/TransitionPrefixer.php`: pass.
- `php -l lanes/lightningcss/tests/TransitionPrefixerTest.php`: pass.
- `php -l lanes/lightningcss/examples/wordpress-media-range-layer-prefixer.php`: pass.
- `php tools/run-tests.php lanes/lightningcss/tests/TransitionPrefixerTest.php`: `1 test files, 1410 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-media-range-layer-prefixer.php --self-test`: pass.
- `php tools/run-tests.php lanes/lightningcss/tests`: `13 test files, 8936 assertions, 0 failures`.
- `git diff --check -- lanes/lightningcss`: pass.

Status delta:
- Full lane `phpPass` moves `8934 -> 8936`.
- Conservative mapped upstream coverage remains `2399 / 3532`; this corrects behavior inside the already represented media-query range/layer cluster.
- Full upstream Rust/Node/WASM runners were not executed.
- Root harness was not run.

Dependency closure:
- No new support component is needed. The slice reuses the native PHP media-query parser, import-tail scanner, and transition prefixer target options.

Non-overlap:
- This avoids accepted `@media` range fallback, layer-statement-before-media fallback, resolution prefixing inside `@media`, x/dppx media serialization, advanced media math, custom-media import-tail substitution, bundle import graph, source-map, CSSOM, CSS Modules, target-prefixing, property-value, and custom at-rule clusters.

Next task:
- Continue with non-overlapping LightningCSS media-query parser recovery or CSSOM/media-list read-write parity, or pivot to source maps, bundle/import graph, CSS Modules, custom at-rules, property/value, target-prefixing, selector, or parser recovery gaps.
