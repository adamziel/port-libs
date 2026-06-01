# Media Query Advanced Unitless Math Range Layer Parity

Slice: `lightningcss-media-query-range-layer-parity-20260601T143817Z`

Base accepted HEAD: `a1793bf843e0ae211c773e9db72ccf77457cd548`

## Source Truth

- Upstream LightningCSS cache: `/home/claude/port-libs/.upstream-cache/lightningcss`
- Upstream commit: `22bdda3d190f1cd321d98026225cfc964af64ad9`
- Targeted native-addon probes confirmed `sqrt()`, `pow()`, `log()`, and `exp()` are accepted as unitless media range values, minified numerically, and lowered for Firefox 60 range fallbacks.
- Probed examples included:
  - `(width >= sqrt(2))` -> `(width>=1.41421)` and Firefox 60 `(min-width:1.41421)`
  - `(width >= pow(2, 3))` -> `(width>=8)` and Firefox 60 `(min-width:8)`
  - `(width >= log(2))` -> `(width>=.693147)` and Firefox 60 `(min-width:.693147)`
  - `(width >= exp(1))` -> `(width>=2.71828)`
  - `(width >= max(pow(2, 3), 4px))` -> `(width>=max(8,4px))` and Firefox 60 `(min-width:max(8,4px))`
  - Dimensional advanced-unitless function inputs such as `pow(2px, 2)` are rejected.

## Implementation

- `MediaQueryParser` now recognizes `sqrt`, `pow`, `log`, and `exp` as media range math functions.
- Unitless advanced math values fold through nested calls, `calc()` inputs, and `e`/`pi` constants.
- Mixed unitless/dimensional `min()` and `max()` expressions preserve the upstream unresolved expression shape instead of coercing unitless math results to `px`.
- Invalid dimensional arguments to the advanced unitless functions are rejected before range serialization.
- Layered target-prefix range lowering reuses the parser output for Firefox/Safari/Opera fallback media queries.

## Verification

- `php tools/run-tests.php lanes/lightningcss/tests/MediaQueryParserTest.php`
  - `1 test files, 704 assertions, 0 failures`
- `php tools/run-tests.php lanes/lightningcss/tests/TransitionPrefixerTest.php`
  - `1 test files, 1348 assertions, 0 failures`
- `php lanes/lightningcss/examples/wordpress-media-range-layer-prefixer.php --self-test`
  - passed
- `php tools/run-tests.php lanes/lightningcss/tests`
  - `13 test files, 8328 assertions, 0 failures`
- `php -l` on changed PHP files
  - passed
- `git diff --check -- lanes/lightningcss`
  - passed

## Status Delta

- `phpPass`: `8290 -> 8328`
- `phpFail`: `0`
- Mapped coverage remains `2393 / 3532`; this deepens the represented media-query range/layer cluster.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PHP `MediaQueryParser`, `CssMinifier`, `TransitionPrefixer`, and WordPress-style media range layer example coverage.

## Non-Overlap

This does not repeat accepted media range sign/abs/hypot, resolution x-unit serialization, redundant calc normalization, typed custom range handling, negated interval lowering, or target prefix property/value batches. It is limited to advanced unitless CSS math functions inside media range/layer behavior.

## Follow-Up

Full upstream Rust/Node/WASM runners were not executed for this isolated micro-slice. A later media-query worker can extend this to broader calc simplification cases such as unresolved mixed-unit `clamp()` if the supervisor asks for a wider math-function pass.
