# LightningCSS Relative Polar Color Alpha Parity - 2026-05-31T21:27Z

Lane: `lightningcss`
Micro-slice: `lightningcss-property-values-color-font-grid-parity-20260531T212724Z`
Base accepted HEAD: `c7ca7ac45660966d9eecf84ad3eaffea66691f11`

## Upstream Source Truth

- Pinned upstream cache: `/home/claude/port-libs/.upstream-cache/lightningcss`
- Upstream commit: `22bdda3d190f1cd321d98026225cfc964af64ad9`
- Targeted source: `src/lib.rs::test_relative_color`

## Behavior Ported

This slice maps a bounded lch/oklch relative-color cluster:

- `alpha` used as the lch/oklch lightness channel serializes as an untyped number, matching upstream outputs such as `lch(1 45 30)` and `oklch(.4 45 30/.4)`.
- explicit `/ l` in polar relative colors preserves the clamped `/1` alpha output where upstream keeps it.
- `lch(from color(display-p3 0 0 0) l c h / alpha)` and `oklch(from color(display-p3 0 0 0) l c h / alpha)` resolve to zero polar colors.

## Local Evidence

- `php tools/run-tests.php lanes/lightningcss/tests/CssMinifierTest.php`
  - `1 test files, 1576 assertions, 0 failures`
- `php tools/run-tests.php lanes/lightningcss/tests`
  - `13 test files, 4463 assertions, 0 failures`
- `php lanes/lightningcss/examples/wordpress-color-value-minifier.php --self-test`
  - exits `0`

## Coverage Delta

- Conservative mapped coverage: `2145 / 3532 -> 2155 / 3532`
- Focused PHP assertion delta: `4453 -> 4463`
- Counted as `+8` newly asserted lch/oklch valid-permutation cases plus `+2` display-p3 black-origin polar cases.

## Dependency Closure

No new support component is needed. The change reuses the existing native PHP `CssMinifier` relative-color parser/evaluator and adds only bounded channel metadata for the upstream polar color serialization edge.
