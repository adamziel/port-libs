# LightningCSS Media Query Range Layer Bundler Boolean Parity

Slice: `lightningcss-media-query-range-layer-parity-20260531T174647Z`

Base: `b1feedb755e93656cf717884940e8c64724c26f1`

## Source Truth

- Pinned upstream: `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Targeted upstream source:
  - `src/bundler.rs`, where nested `@import` rules combine parent and child media lists through `media.and(&import.media)` before wrapping the imported stylesheet.
  - `src/media_query.rs` `MediaQuery::and`, where media types are simplified before appending conditions. Examples include `print and not screen` becoming `print`, `screen and print` becoming `not all`, `not screen and not screen` remaining `not screen`, and incompatible negated media types returning an error.

## Red-First Evidence

Before this patch, local probes through `CssBundler::bundle()` showed string-concatenated media clauses instead of upstream media-type boolean semantics:

- Parent `print` plus child `not screen and (width >= 240px)` emitted `@media print and not screen and (width>=240px)` instead of `@media print and (width>=240px)`.
- Parent `screen` plus child `print` emitted `@media screen and print` instead of simplifying to the never-matching `not all` branch that the minifier drops.
- Parent `not screen` plus child `not screen and (width >= 240px)` threw `unsupported-media-boolean-logic`; upstream keeps `not screen and (width>=240px)`.
- Parent `not all` plus child `screen` emitted `@media not all and screen`; upstream simplifies to `not all`, which is dropped when unconditionally never matching.

## Native Delta

- `CssBundler` now parses parent/child import media fragments through the existing `MediaQueryParser` before combining them.
- Bundled import media conjunction now mirrors upstream `MediaQuery::and` for media type qualifiers, including `not all`, same negated type, conflicting concrete media types, and mixed negated/concrete types.
- Child range conditions remain attached after media-type simplification, so layered imports preserve upstream range output such as `@media print and (width>=240px){@layer ...}`.
- Unsupported boolean logic remains bounded to incompatible negated media types such as `not print` combined with `not screen`.
- The WordPress import graph smoke now covers a layered block-style import where a parent `print` import and child `not screen` range import simplify to the upstream-compatible `print` range.

## Verification

- `php -l lanes/lightningcss/src/CssBundler.php && php -l lanes/lightningcss/tests/CssBundlerTest.php && php -l lanes/lightningcss/examples/wordpress-bundle-import-graph.php`
  - Result: no syntax errors
- `php tools/run-tests.php lanes/lightningcss/tests/CssBundlerTest.php`
  - Result: `1 test files, 151 assertions, 0 failures`
- `php tools/run-tests.php lanes/lightningcss/tests/MediaQueryParserTest.php lanes/lightningcss/tests/TransitionPrefixerTest.php`
  - Result: `2 test files, 572 assertions, 0 failures`
- `php tools/run-tests.php lanes/lightningcss/tests`
  - Result: `13 test files, 2798 assertions, 0 failures`
- `php lanes/lightningcss/examples/wordpress-bundle-import-graph.php --self-test`
  - Result: exited `0`; emitted `media-boolean-layer-range: simplified`

Root harness status: not run - isolated micro-slice.

## Status Delta

- Full LightningCSS PHP evidence: `2794 -> 2798 pass / 0 fail`.
- Conservative mapped coverage remains `1601 / 3532`; this deepens the already mapped media-query/import-graph range-layer cluster rather than claiming a new upstream denominator row.

## Non-Overlap

This avoids accepted direct media range parsing/fallback, typed/unknown/equality ranges, include/exclude flags, resolution prefixes and `x` units, qualifier range lowering, parenthesized negation, all-media elision, import validation, custom-media, CSS Modules, SourceMap, CSSOM, color/font/grid/property, custom-at-rule, and target-prefixing slices. The stale May 25 CustomMedia rework note under the main handoff directory targets an old import-tail conflict and does not overlap this bundler media-type conjunction fix.

## Dependency Closure

No new support component is needed. The slice reuses the native `CssBundler`, `MediaQueryParser`, `CssMinifier`, existing import scanner, and lane-local tests/examples. No Node, Rust, browser, WASM runtime, parser generator, or external service is required.

## Next Task

Continue with non-overlapping LightningCSS media-query parser/minifier, bundler/import graph, CSSOM, CSS Modules, SourceMap, target-prefixing, property-value/font/grid/color, and custom-at-rule parity. No current blocker was introduced by this slice.
