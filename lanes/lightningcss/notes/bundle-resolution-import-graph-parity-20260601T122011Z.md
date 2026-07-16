# Bundle Resolution Import Graph Parity - 2026-06-01T122011Z

## Slice

Pinned CSS Modules dependency inline source-map remapping through the bundle/import graph.

Upstream source truth is `parcel-bundler/lightningcss` at manifest commit
`22bdda3d190f1cd321d98026225cfc964af64ad9`. In `src/bundler.rs`,
`load_file()` applies the same source-map source collection rule to every
loaded stylesheet, and CSS Modules `composes ... from` dependencies are loaded
through `add_css_module_dep()`. That means a CSS Modules dependency with a
`sourceMappingURL=data:` input map should remap to the original source from the
input map rather than exposing the generated dependency stylesheet path.

## Implementation

- Added focused PHP coverage in `CssBundlerTest.php` for a CSS Modules
  `composes` dependency whose imported stylesheet carries an inline source map.
- Added the same WordPress-facing smoke path to
  `wordpress-bundle-import-graph.php`, asserting that a block module dependency
  remaps to `modules/_tokens.scss` while preserving the composed export.
- No production source change was required; the existing bundler/source-map
  path already matched the upstream behavior, and this slice pins it against
  regression.

## Evidence

- `php -l lanes/lightningcss/tests/CssBundlerTest.php`
  - `No syntax errors detected in lanes/lightningcss/tests/CssBundlerTest.php`
- `php -l lanes/lightningcss/examples/wordpress-bundle-import-graph.php`
  - `No syntax errors detected in lanes/lightningcss/examples/wordpress-bundle-import-graph.php`
- `php tools/run-tests.php lanes/lightningcss/tests/CssBundlerTest.php`
  - `1 test files, 736 assertions, 0 failures`
- `php lanes/lightningcss/examples/wordpress-bundle-import-graph.php --self-test`
  - includes `css-modules-dependency-source-map: remapped`
- `php tools/run-tests.php lanes/lightningcss/tests`
  - `13 test files, 7703 assertions, 0 failures`

## Status Delta

- Focused bundler assertions increased from `727` to `736` (`+9`).
- Full LightningCSS lane assertions increased from `7694` to `7703` (`+9`).
- `lane-status.json` `phpPass` updated from `7694` to `7703`.
- Conservative mapped upstream coverage remains `2374 / 3532`; this is parity
  coverage for an already mapped bundle/import graph behavior, not a new
  manifest denominator row.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PHP
CSS bundler, CSS Modules dependency loader, and `SourceMap` VLQ/remapping
support.

## Non-Overlap

This avoids the accepted malformed inline data source-map suppression,
source-map pruning, import media range tails, CSSOM/custom-at-rule, target
prefixing, and prior CSS Modules import graph diagnostics. Remaining adjacent
bundle/import graph work should target generated source-map offsets through
final bundle printing or resolver diagnostic ordering edges, not this CSS
Modules dependency input-map remap path.
