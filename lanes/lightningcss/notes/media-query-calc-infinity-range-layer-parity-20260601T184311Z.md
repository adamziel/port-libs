# Media Query Calc Infinity Range Layer Parity

Micro-slice: `lightningcss-media-query-range-layer-parity-20260601T184311Z`

## Source Truth

- Base accepted HEAD for this worktree: `4cbd19204f0f849ce2c2efa0ea77036ddc64c707`.
- Pinned upstream: `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Upstream native addon probes from the pinned checkout show CSS calc constants are accepted inside media range math and serialized before range lowering:
  - `@layer blocks { @media (width >= calc(infinity * 1px)) { ... } }` minifies to `(width>=3.40282e38px)`.
  - `@layer blocks { @media (calc(-infinity * 1px) <= width <= calc(infinity * 1px)) { ... } }` minifies to `(-3.40282e38px<=width<=3.40282e38px)`.
  - `@layer blocks { @media (width >= infinity) { ... } }` rejects as an invalid media query.

## Red-First Gap

Before this slice, the PHP media-query parser preserved `calc(infinity * 1px)` and related multiplicative calc constants verbatim inside range values. Legacy target fallback then lowered the unfurled `calc(...)` string rather than the upstream finite sentinel value.

## Implementation

- Extended `MediaQueryParser` simple multiplicative `calc()` folding to parse `infinity`, `-infinity`, and `NaN` constants inside multiplication and division.
- Preserved existing invalid range guards for two dimensional operands, division by dimensional operands, percentages in length ranges, and bare `infinity` outside `calc()`.
- Preserved upstream-style large scientific notation such as `3.40282e38` instead of expanding it to a huge decimal.
- Added focused parser, minifier, range-lowering, and invalid-value assertions for layered media range values.
- Extended the WordPress media range/layer prefixer smoke with calc-infinity range fallback, calc-infinity interval fallback, and a bare infinity invalid-value guard.

## Verification

```text
php tools/run-tests.php lanes/lightningcss/tests/MediaQueryParserTest.php
1 test files, 769 assertions, 0 failures

php lanes/lightningcss/examples/wordpress-media-range-layer-prefixer.php
passed; printed the expected media range/layer smoke outputs

php tools/run-tests.php lanes/lightningcss/tests
13 test files, 8946 assertions, 0 failures

php -l lanes/lightningcss/src/MediaQueryParser.php
No syntax errors detected in lanes/lightningcss/src/MediaQueryParser.php

php -l lanes/lightningcss/tests/MediaQueryParserTest.php
No syntax errors detected in lanes/lightningcss/tests/MediaQueryParserTest.php

php -l lanes/lightningcss/examples/wordpress-media-range-layer-prefixer.php
No syntax errors detected in lanes/lightningcss/examples/wordpress-media-range-layer-prefixer.php

php -r 'json_decode(file_get_contents("lanes/lightningcss/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "lightningcss json OK\n";'
lightningcss json OK

git diff --check -- lanes/lightningcss
passed with no output
```

## Status Delta

- `lane-status.json` `phpPass` moves from `8934` to `8946` based on the full focused LightningCSS lane run.
- Conservative mapped coverage remains `2399 / 3532`; this deepens an existing media-query range/layer cluster rather than claiming a new denominator row.
- Root harness, upstream Rust, Node, and WASM runners were not executed for this isolated micro-slice.

## Dependency Closure

No new support component is needed. The slice reuses the existing PHP media-query parser, calc folder, range lowerer, target prefixer, and WordPress example harness. The pinned upstream native addon was used only as a source-truth oracle, not as a runtime dependency.

## Non-Overlap

This does not repeat accepted media range decimal/scientific notation, environment variable ranges, redundant calc parentheses, sign/advanced math functions, resolution prefixing, typed/custom/unknown ranges, import media tails, layer-statement range fallback, or invalid percentage recovery. It is limited to CSS calc constants inside media range values and their layered target fallbacks.

## Next Task

Continue non-overlapping LightningCSS media-query parity around parser recovery, range serialization, or remaining calc/math serialization edges, or pivot to current-base source-map, bundle/import, CSSOM, CSS Modules, custom-at-rule, target-prefixing, selector, parser recovery, or property/value gaps.
