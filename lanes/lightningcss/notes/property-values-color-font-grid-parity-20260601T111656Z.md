# LightningCSS property values color/font/grid parity - 2026-06-01T11:16:56Z

Micro-slice: `lightningcss-property-values-color-font-grid-parity-20260601T111656Z`

## Source truth

- Upstream source: `parcel-bundler/lightningcss` pinned manifest commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Relevant upstream files:
  - `src/properties/font.rs`: `FontStretchKeyword` maps CSS font-stretch keywords to percentages, and `FontStretch::to_css` serializes through `Percentage` when minifying.
  - `src/lib.rs::test_font_face`: adjacent accepted descriptor behavior covers `@font-face` `font-stretch` ranges and identical-range collapse (`50% 200%`, `50% 50%`).

## Delta

- Added endpoint normalization for two-token `font-stretch` ranges in `CssMinifier`, reusing the existing single-token keyword mapping.
- New behavior examples:
  - `condensed expanded` -> `75% 125%`
  - `expanded expanded` -> `125%`
  - `semi-condensed 125%` -> `87.5% 125%`
  - `50.0% ultra-expanded` -> `50% 200%`
- Updated the WordPress font-face source/range smoke so a condensed variable font face exercises the range endpoint normalization.

## Evidence

Before implementation, this current-base probe showed the missing parity:

```text
php -r 'require "tools/bootstrap.php"; $class = "PortLibs\\LightningCSS\\CssMinifier"; $m = new $class(); echo $m->minify("@font-face { font-stretch: condensed expanded; }"), PHP_EOL;'
@font-face{font-stretch:condensed expanded}
```

Focused verification after the patch:

```text
php -l lanes/lightningcss/src/CssMinifier.php
No syntax errors detected in lanes/lightningcss/src/CssMinifier.php

php -l lanes/lightningcss/tests/CssMinifierTest.php
No syntax errors detected in lanes/lightningcss/tests/CssMinifierTest.php

php -l lanes/lightningcss/examples/wordpress-font-face-src-range-minifier.php
No syntax errors detected in lanes/lightningcss/examples/wordpress-font-face-src-range-minifier.php

php tools/run-tests.php lanes/lightningcss/tests/CssMinifierTest.php
1 test files, 1947 assertions, 0 failures

php lanes/lightningcss/examples/wordpress-font-face-src-range-minifier.php --self-test
exited 0 and printed the expected minified CSS, including `font-stretch:75% 125%`

php tools/run-tests.php lanes/lightningcss/tests
13 test files, 7530 assertions, 0 failures

php -r '$data = json_decode(file_get_contents("lanes/lightningcss/lane-status.json"), true); if (!is_array($data)) { fwrite(STDERR, json_last_error_msg() . PHP_EOL); exit(1); } echo "lane-status json ok\n";'
lane-status json ok

git diff --check -- lanes/lightningcss
passed with no output
```

## Status

- `lane-status.json` updated from `phpPass: 7526` to `phpPass: 7530`.
- Conservative mapped coverage remains `2369 / 3532`; this is a focused parity assertion inside the already mapped font-face descriptor family, not a new manifest denominator row.
- Root harness: not run - isolated micro-slice.
- Rust/Node/WASM upstream runners: not run in this isolated lane.

## Non-overlap

This slice avoids the accepted/recent LightningCSS property-value clusters for alpha color target fallbacks, custom-property alpha fallbacks, font target fallback boundaries, oblique default-angle handling, grid auto-flow/default shorthand composition, grid residual placement values, and grid track-list composition. It only touches the font-stretch descriptor range serializer path.

## Dependency closure

No new support component is needed. The behavior is implemented with the existing PHP CSS minifier tokenizer and numeric dimension serializer.

## Next task

Continue with a non-overlapping upstream-backed property-value edge, preferably remaining color/font/grid serialization gaps that can add focused PHP assertions without changing conservative mapped coverage unless a new manifest row is truly admitted.
