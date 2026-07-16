# LightningCSS property values color/font/grid parity 2026-06-01T11:33:13Z

Status: ready for integration.

## Scope

This isolated property-value slice deepens the already represented LightningCSS font value cluster. It ports upstream `font-stretch` minified serialization for `normal` and mixed keyword/percentage range endpoints.

Source-truth reads from pinned upstream `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`:

- `src/properties/font.rs`: `FontStretch::to_css` serializes every stretch as a `Percentage` while minifying, so standalone `font-stretch: normal` prints as `100%`.
- `src/properties/font.rs`: `Font::to_css` omits `FontStretch::default()` before serializing a font shorthand, so explicit longhand `font-stretch: normal` remains omitted when the longhand set composes into `font`.
- `src/values/size.rs`: `Size2D<T>::to_css` writes the second range endpoint only when the parsed endpoint values differ, so `expanded expanded` collapses but `expanded 125%` keeps two serialized endpoints.

Pre-fix focused probe on this worktree showed the gap:

- `@font-face {font-stretch: normal}` -> `@font-face{font-stretch:normal}`
- `@font-face {font-stretch: normal 100%}` -> `@font-face{font-stretch:normal 100%}`
- `@font-face {font-stretch: expanded 125%}` -> `@font-face{font-stretch:125%}`
- `.foo { font-stretch: normal; }` -> `.foo{font-stretch:normal}`

## Implementation

- `CssMinifier` now keeps font-stretch keyword identity through the early declaration-value pass, so font shorthand composition can still distinguish `normal` from `100%`.
- A final focused font-stretch declaration pass serializes remaining longhands and `@font-face` descriptors with upstream minified percentage output.
- Mixed keyword/percentage font-stretch ranges collapse only when their parsed identities match, not merely when both endpoints serialize to the same percentage.
- The WordPress font-face example now includes a normal-axis variable range that minifies to `font-stretch:100% 100%`.

## Evidence

- `php -l lanes/lightningcss/src/CssMinifier.php` -> no syntax errors
- `php -l lanes/lightningcss/tests/CssMinifierTest.php` -> no syntax errors
- `php -l lanes/lightningcss/examples/wordpress-font-face-src-range-minifier.php` -> no syntax errors
- `php tools/run-tests.php lanes/lightningcss/tests/CssMinifierTest.php` -> 1 test file, 1958 assertions, 0 failures
- `php lanes/lightningcss/examples/wordpress-font-face-src-range-minifier.php --self-test` -> passed and printed the expected minified CSS
- `php tools/run-tests.php lanes/lightningcss/tests` -> 13 test files, 7589 assertions, 0 failures
- `git diff --check -- lanes/lightningcss` -> pass

## Status Delta

- `lane-status.json` `phpPass`: 7582 -> 7589
- `phpFail`: 0
- Conservative mapped coverage remains 2374 / 3532 because this deepens the already represented font/property-value cluster.

## Non-overlap

The prior accepted property-values slice covered font-stretch keyword endpoint normalization such as `condensed expanded` and `expanded expanded`. This slice is limited to `normal` minified percentage serialization and mixed keyword/percentage endpoint identity. It does not touch source maps, bundle/import graph, CSS Modules, custom at-rules, media queries, grid behavior, or target-prefix code.

## Dependency Closure

No new support component is needed. The existing native PHP `CssMinifier` and focused WordPress font-face smoke cover this behavior without Node/WASM.

## Next

Continue with remaining upstream-backed property-value parity gaps in color/font/grid only when they add distinct behavior beyond the represented font-stretch, grid shorthand, and color serialization clusters.
