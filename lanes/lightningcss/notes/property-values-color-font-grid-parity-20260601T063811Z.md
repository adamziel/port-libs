# LightningCSS Property Values - LCH/OKLCH color-mix tail parity

Slice: `lightningcss-property-values-color-font-grid-parity-20260601T063811Z`
Base accepted HEAD: `263ff1b299519d64e76087161433531b7a3e8cf2`
Pinned upstream: `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`

## Source Truth

- Focused upstream function: `src/lib.rs::test_color_mix`.
- Targeted pristine reads used `git -C /home/claude/port-libs/.upstream-cache/lightningcss show 22bdda3d190f1cd321d98026225cfc964af64ad9:src/lib.rs`.
- The covered helper cluster is the LCH/OKLCH polar `color-mix()` tail: right-side percentage spelling, weighted alpha interpolation with `none`, full default/shorter/longer/increasing/decreasing/specified hue-direction permutations, and missing polar channel interpolation.

## Changes

- Added 70 focused PHP assertions to `CssMinifierTest.php` for pinned upstream LCH/OKLCH `color-mix()` cases that were not directly locked by the accepted lane tests.
- Extended `wordpress-color-value-minifier.php` so the WordPress color smoke exercises the same native PHP polar hue and alpha-`none` minifier paths.
- No `CssMinifier` production source change was needed: pre-edit probes showed the existing native path already serialized these upstream cases correctly.

## Non-Overlap

This slice does not repeat accepted HWB hue interpolation, HSL remaining hue/none variants, advanced-origin HWB color-mix, XYZ fallback lowering, relative color, font target fallback, grid auto-flow/residual, CSSOM, media-query, target-prefix, source-map, bundle/import graph, CSS Modules, or custom at-rule work.

Conservative mapped coverage remains `2360 / 3532` because the LCH/OKLCH polar `color-mix()` denominator cluster was already represented in the manifest. This patch is PHP assertion growth and WordPress smoke coverage for still-unlocked helper cases inside that represented cluster.

## Verification

- `php -l lanes/lightningcss/tests/CssMinifierTest.php && php -l lanes/lightningcss/examples/wordpress-color-value-minifier.php` -> no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/CssMinifierTest.php` -> `1 test files, 1891 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-color-value-minifier.php --self-test` -> exits 0 and emits the expected minified CSS.
- `php tools/run-tests.php lanes/lightningcss/tests` -> `13 test files, 6528 assertions, 0 failures`.
- `git diff --check -- lanes/lightningcss` -> passes.

## Dependency Closure

No new support component is needed. The slice reuses the native PHP `CssMinifier` color parser, polar color-space color-mix interpolation, hue-direction mixing, and color serialization paths.

## Follow-Up

Full upstream Rust, Node, and WASM runners were not executed for this isolated micro-slice. Continue with non-overlapping property-value/font/grid gaps or pivot to source-map, CSS Modules, bundle/import graph, media query, CSSOM, custom at-rule, and target-prefix parity.
