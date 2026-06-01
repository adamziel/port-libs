# Media Query Redundant Calc Range Layer Parity

Micro-slice: `lightningcss-media-query-range-layer-parity-20260601T132245Z`

## Source Truth

- Base accepted HEAD for this worktree: `dcd80e5266331133716e275b7c2bf49d3b974fd7`.
- Pinned upstream: `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Upstream native addon probes from the pinned checkout show redundant parentheses inside media-query `calc()` range values are stripped before simple calc folding and target lowering. Examples:
  - `@layer blocks { @media (width >= calc((2px + 4px))) { ... } }` minifies to `(width>=6px)` and lowers to `(min-width:6px)` for Firefox 60.
  - `@layer blocks { @media (calc((2px + 4px)) <= width <= calc((10px + 2px))) { ... } }` minifies to `(6px<=width<=12px)` and lowers to `(min-width:6px) and (max-width:12px)` for Firefox 60/85.
  - `@layer blocks { @media (aspect-ratio >= calc((1 / 2))) { ... } }` minifies to `(aspect-ratio>=.5)` and lowers to `(min-aspect-ratio:.5)` for Firefox 60.

## Red-First Gap

Before this slice, the PHP parser preserved `calc((...))` wrappers in media range values, so layered target fallback output kept `calc((2px + 4px))` instead of folding to `6px`. Ratio forms such as `calc((1 / 2))` also failed typed range validation before they could lower to upstream-compatible aspect-ratio fallbacks.

## Implementation

- Added `MediaQueryParser::normalizeSimpleCalcWrapper()` to canonicalize whole-value `calc(...)` expressions by stripping redundant balanced parentheses around the inner expression before existing simple calc folders run.
- Reused the existing same-unit, multiplicative, unitless, and invalid-multiplicative calc classifiers after that normalization.
- Added focused media-query parser assertions for same-unit length folding, multiplicative folding, interval folding, mixed-unit normalization, typed ratio folding, unknown ratio folding, and layered minifier output.
- Extended target-prefix fallback assertions for Firefox/Chrome media ranges inside `@layer` rules.
- Extended the WordPress layered media calc example to cover nested length calc, nested interval calc, and aspect-ratio calc fallbacks.

## Verification

```text
php tools/run-tests.php lanes/lightningcss/tests/MediaQueryParserTest.php
1 test files, 631 assertions, 0 failures

php tools/run-tests.php lanes/lightningcss/tests/TransitionPrefixerTest.php
1 test files, 1308 assertions, 0 failures

php lanes/lightningcss/examples/wordpress-media-calc-range-layer-prefixer.php --self-test
passed; printed expected chrome85/firefox64/firefox85 outputs

php tools/run-tests.php lanes/lightningcss/tests
13 test files, 8042 assertions, 0 failures

php -l lanes/lightningcss/src/MediaQueryParser.php
No syntax errors detected in lanes/lightningcss/src/MediaQueryParser.php

php -l lanes/lightningcss/tests/MediaQueryParserTest.php
No syntax errors detected in lanes/lightningcss/tests/MediaQueryParserTest.php

php -l lanes/lightningcss/tests/TransitionPrefixerTest.php
No syntax errors detected in lanes/lightningcss/tests/TransitionPrefixerTest.php

php -l lanes/lightningcss/examples/wordpress-media-calc-range-layer-prefixer.php
No syntax errors detected in lanes/lightningcss/examples/wordpress-media-calc-range-layer-prefixer.php

php -r 'json_decode(file_get_contents("lanes/lightningcss/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json OK\n";'
lane-status json OK

git diff --check -- lanes/lightningcss
passed with no output
```

## Status Delta

- `lane-status.json` `phpPass` moves from `8018` to `8042` based on the full focused LightningCSS lane run.
- Conservative mapped coverage remains `2392 / 3532`; this deepens an existing media-query target-fallback row rather than claiming a new upstream manifest row.
- Rust/Node/WASM upstream runners were not executed in this isolated micro-slice.

## Dependency Closure

No new support component is needed. The slice reuses the native PHP media-query parser, calc folders, range fallback lowerer, prefixer, and WordPress example harness. The pinned upstream native addon was used only as a source-truth oracle, not as a runtime dependency.

## Non-Overlap

This does not repeat accepted media-range decimals, scientific notation, environment variables, math functions, resolution unit fallback, import media tails, invalid percentage recovery, custom media, CSSOM, CSS Modules, custom at-rule visitors, source maps, bundle/import graph, selector prefixing, or property/value target fallback work. It is limited to redundant `calc((...))` parentheses in media range values and their layered target fallbacks.

## Next Task

Continue non-overlapping media-query parity around parser recovery, range serialization, or target fallback edges that are still unmapped in the upstream manifest, or pivot to current-base source-map, bundle/import, CSSOM, CSS Modules, custom-at-rule, or property/value gaps.
