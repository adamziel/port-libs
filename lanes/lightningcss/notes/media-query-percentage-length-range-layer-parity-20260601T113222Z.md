# LightningCSS Media Query Percentage Length Range Layer Parity

Slice: `lightningcss-media-query-range-layer-parity-20260601T113222Z`

Base: `643db6cd7b3a41ab8e3a67fdda031493c589be65`

## Source Truth

- Pinned upstream: `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Targeted upstream source:
  - `src/media_query.rs`, where known length media features parse through `MediaFeatureType::Length => Length::parse(input)`.
  - `src/values/length.rs`, where `LengthValue::parse` accepts dimensions or numbers for `<length>` while percentage support lives under the separate `LengthPercentage` type.
- Local upstream native oracle from `/home/claude/port-libs/.upstream-cache/lightningcss/lightningcss.linux-x64-gnu.node` confirmed:
  - `@media (width >= 50%) { ... }` rejects with `Unexpected token Percentage`.
  - `@layer blocks { @media (width >= 50%) { ... } }` rejects with the same percentage token diagnostic.
  - `@media (50% <= width <= 75%) { ... }` rejects at the first percentage token.
  - `@media (width >= max(10%, 20%)) { ... }` and `@media (width >= calc(50% + 1px)) { ... }` reject percentage-based math for a known length media feature.
  - `@media (width >= calc(10px + 1rem)) { ... }` still serializes to `@media (min-width:calc(10px + 1rem)){...}` for legacy range fallback targets.

## Native Delta

- `MediaQueryParser` now rejects percentages in known length media range and legacy min/max feature values.
- Length media range validation now rejects percentage-bearing `calc()`, `min()`, `max()`, `clamp()`, and related math functions instead of accepting or folding them into invalid legacy min/max-width values.
- Recovery mode still treats these values as recoverable invalid media feature values and preserves the rule with an `Invalid media query` warning, matching the lane's existing PHP recovery model.
- The WordPress media range/layer recovery example now includes a percentage breakpoint block to prove invalid percentage length ranges are warned while later valid length math still lowers to `width>=3px`.

## Verification

- `php -l lanes/lightningcss/src/MediaQueryParser.php`
  - Result: no syntax errors.
- `php -l lanes/lightningcss/tests/MediaQueryParserTest.php`
  - Result: no syntax errors.
- `php -l lanes/lightningcss/tests/CssMinifierTest.php`
  - Result: no syntax errors.
- `php -l lanes/lightningcss/examples/wordpress-media-range-layer-recovery.php`
  - Result: no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/MediaQueryParserTest.php lanes/lightningcss/tests/CssMinifierTest.php`
  - Result: `2 test files, 2530 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-media-range-layer-recovery.php --self-test`
  - Result: `OK`.
- `php tools/run-tests.php lanes/lightningcss/tests`
  - Result: `13 test files, 7597 assertions, 0 failures`.
- `git diff --check -- lanes/lightningcss`
  - Result: exited `0`.

Root harness status: not run - isolated micro-slice.

## Status Delta

- Full LightningCSS PHP evidence: `7582 -> 7597 pass / 0 fail`.
- Conservative mapped coverage remains `2374 / 3532`; this deepens the represented media-query range/recovery cluster rather than claiming a new denominator row.

## Non-Overlap

- Does not repeat accepted compact negation recovery, bare-not operand validation, resolution/x-unit serialization, ratio/math range folding, environment variable range values, target-prefix media fallback lowering, bundle/import graph media propagation, CSS Modules, CSSOM, SourceMap, property-value, custom-at-rule, or selector-prefix clusters.

## Dependency Closure

No new support component is needed. The slice reuses native PHP media-query validation, the existing error-recovery warning path, focused lane tests, the WordPress media range/layer recovery example, and a local pinned upstream native oracle only for source-truth confirmation.

## Next Task

Continue with non-overlapping LightningCSS media-query parser/recovery, target-prefix browser-boundary cases, CSSOM, CSS Modules, SourceMap, bundler, property-value/font/grid/color, or custom-at-rule parity.
