# LightningCSS Alpha Color Target Fallback Parity

Micro-slice: `lightningcss-property-values-color-font-grid-parity-20260531T153145Z`
Accepted base: `f19de273d07b6a4933953049cdd208ef1fd51490`

## Upstream Source Truth

- Pinned upstream: `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Targeted pristine read: `git -C /home/claude/port-libs/.upstream-cache/lightningcss show 22bdda3d190f1cd321d98026225cfc964af64ad9:src/lib.rs | sed -n '18447,18560p'`.
- Mapped 10 upstream `src/lib.rs::test_color` alpha-color target fallback helper cases. The non-minifying `attr_test(..., false, ...)` case remains outside the current minifying PHP public path.

## Native PHP Delta

- `CssMinifier` clamps RGB numeric and percentage channels before color serialization, matching upstream overflow handling for `rgba(123,456,789,.5)`.
- `TransitionPrefixer` lowers alpha hex colors to compact `rgba(...)` for old IE/Chrome/Firefox/iOS/Safari targets without `#rrggbbaa` support and lowers black fully transparent values to `transparent`.
- `wordpress-alpha-color-fallback.php` models block cover/button alpha color delivery for Chrome 61, Chrome 95, and IE 11 without Node.

## Evidence

- `php tools/run-tests.php lanes/lightningcss/tests/TransitionPrefixerTest.php` -> `1 test files, 324 assertions, 0 failures`.
- `php tools/run-tests.php lanes/lightningcss/tests/CssMinifierTest.php` -> `1 test files, 874 assertions, 0 failures`.
- `php tools/run-tests.php lanes/lightningcss/tests` -> `13 test files, 1928 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-alpha-color-fallback.php --self-test` -> `OK`.

## Non-Overlap

This slice does not repeat accepted srgb/lab/oklab `color-mix()` minification, advanced Color 4 value minification, color-scheme/light-dark fallbacks, background-clip target prefixing, font-face/font-palette/font-feature slices, or grid shorthand/longhand composition.

## Dependency Closure

No new support component is needed. This reuses the bounded native `CssMinifier` color serializer, `TransitionPrefixer` target-option flow, declaration parser, and scanner helpers.
