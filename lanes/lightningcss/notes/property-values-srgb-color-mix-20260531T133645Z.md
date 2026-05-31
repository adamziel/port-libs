# LightningCSS sRGB Color Mix Value Parity

Micro-slice: `lightningcss-property-values-color-font-grid-parity-20260531T133645Z`

Source truth:

- Upstream pinned commit: `parcel-bundler/lightningcss` `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Targeted source read: `/home/claude/port-libs/.upstream-cache/lightningcss/src/lib.rs`, `src/lib.rs::test_color_mix` around lines 21075-21380.

Implemented behavior:

- `CssMinifier` now evaluates the bounded `color-mix(in srgb, ...)` subset when both color stops are concrete sRGB colors after normal color minification.
- It handles default 50/50 weights, one explicit stop weight, explicit stop weights whose sum is below or above 100%, premultiplied alpha mixing, and compact hex-alpha serialization.
- It preserves unresolved upstream stop mixes for `currentColor` and lowercased `accentcolor` instead of incorrectly reducing them.

Focused evidence:

- `php -l lanes/lightningcss/src/CssMinifier.php`
- `php -l lanes/lightningcss/tests/CssMinifierTest.php`
- `php -l lanes/lightningcss/examples/wordpress-color-value-minifier.php`
- `php tools/run-tests.php lanes/lightningcss/tests/CssMinifierTest.php` => `1 test files, 796 assertions, 0 failures`
- `php lanes/lightningcss/examples/wordpress-color-value-minifier.php` => exits 0

Coverage delta:

- Focused PHP assertions: `788 -> 796` in `CssMinifierTest.php` (+8).
- Conservative mapped coverage: `1130 / 3532 -> 1138 / 3532` (+8 focused upstream `test_color_mix` checks).
- Expected full lane pass evidence after integration: `1525 -> 1533 pass / 0 fail`.

Dependency closure:

- No new support component is needed. The slice reuses the existing native declaration value scanner, color function normalizer, top-level function reader, and hex color serializer.

Non-overlap:

- Avoids accepted advanced Color 4 function serialization, accepted `light-dark()` fallback prefixing, accepted legacy text/sticky prefixes, accepted resolution media-query prefixes, bundle import-prelude diagnostics, custom at-rule visitor behavior, CSS Modules composes delimiter strictness, and CSSOM inset/background/border/transition shorthand parity.
- Remaining color-mix work is the broader non-sRGB interpolation matrix, missing-channel `none` interpolation, custom-property color-mix minification, and target fallback generation outside the already accepted `light-dark()` fallback path.
