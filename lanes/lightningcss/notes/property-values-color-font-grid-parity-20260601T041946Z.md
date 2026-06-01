# LightningCSS Property Values: Relative HSL Byte Parity

## Source Truth

- Upstream: `parcel-bundler/lightningcss` pinned by `UPSTREAM_TEST_MANIFEST.json` at `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Pinned upstream cases: `src/lib.rs::test_relative_color` includes `hsl(from rebeccapurple h s 20% / alpha)` and `hsl(from rgb(20%, 40%, 60%, 80%) h s 20% / alpha)`.
- Pinned implementation shape: `src/values/color.rs` converts HSL through `cssparser_color::hsl_to_rgb`; `cssparser-color 0.5.0` uses the `m1`/`m2` hue helper rather than the shorter chroma/x helper.

## Delta

- Replaced the PHP HSL-to-RGB byte helper with the upstream hue-helper algorithm so half-byte cases match LightningCSS output.
- Added focused assertions for direct HSL byte output plus relative HSL lightness replacement from `rebeccapurple` and `rgb(20%, 40%, 60%, 80%)`.
- Extended the WordPress color value example with the relative HSL lightness replacement output.

## Non-Overlap

- This slice is limited to HSL byte conversion for color property values.
- It does not touch grid/font composition, target prefixing, CSSOM, bundle/import, or source-map behavior.
- The stale May 25 `CustomMediaTransformer.php` rework note is unrelated to this property-value slice.

## Verification

- `php -l lanes/lightningcss/src/CssMinifier.php` passed.
- `php -l lanes/lightningcss/tests/CssMinifierTest.php` passed.
- `php -l lanes/lightningcss/examples/wordpress-color-value-minifier.php` passed.
- `php tools/run-tests.php lanes/lightningcss/tests/CssMinifierTest.php` passed: `1 test files, 1762 assertions, 0 failures`.
- `php tools/run-tests.php lanes/lightningcss/tests` passed: `13 test files, 5948 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-color-value-minifier.php --self-test` passed.

## Dependency Closure

No new support component is needed. The slice reuses the native PHP `CssMinifier` color conversion path.

## Follow-Up

Full upstream Rust/Node/WASM runners were not run in this isolated lane. Continue property-value parity on non-overlapping color/function, font, and grid cases.
