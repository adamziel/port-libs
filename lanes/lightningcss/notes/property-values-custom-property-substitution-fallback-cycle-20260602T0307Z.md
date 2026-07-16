# LightningCSS custom property substitution fallback/cycle parity

Micro-slice: `lightningcss-custom-properties-fallback-cycle-current-base-20260602T0307Z`

Source truth:
- Upstream `parcel-bundler/lightningcss` pinned commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Targeted `src/lib.rs::test_substitute_vars`, which has six helper calls covering direct `var()` substitution, nested fallback resolution, fallback-of-fallback resolution, `calc()` folding after substitution, nested variable resolution, and cycle preservation.

Implementation:
- Added `CssMinifier::substituteVariables($property, $value, $variables)`.
- Substitution walks `var()` functions through declaration token strings, skips quoted strings and `url(...)`, resolves fallbacks recursively, and preserves the current `var(...)` token when a variable cycle is detected.
- The substituted declaration value is then passed through the existing native declaration-value minifier, so upstream results such as `yellow -> #ff0` and `calc(2px + 4px) -> 6px` are preserved.

WordPress smoke:
- Added `examples/wordpress-custom-property-substitution.php`.
- It resolves block theme design tokens for preset color, computed button spacing, fallback gradient output, and verifies cycle preservation for theme token loops.

Verification:
- `php -l lanes/lightningcss/src/CssMinifier.php` - pass
- `php -l lanes/lightningcss/tests/CssMinifierTest.php` - pass
- `php -l lanes/lightningcss/examples/wordpress-custom-property-substitution.php` - pass
- `php tools/run-tests.php lanes/lightningcss/tests/CssMinifierTest.php` - `1 test files, 2174 assertions, 0 failures`
- `php lanes/lightningcss/examples/wordpress-custom-property-substitution.php --self-test` - `OK`
- `php tools/run-tests.php lanes/lightningcss/tests` - `14 test files, 9280 assertions, 0 failures`

Status delta:
- Focused PHP assertions move from `9273` to `9280` (`+7`).
- Conservative mapped coverage moves from `2439 / 3532` to `2445 / 3532` for the six pinned upstream `test_substitute_vars` helper calls.

Non-overlap:
- This does not repeat accepted custom-property color-token minification, custom-property advanced-color target fallbacks, CSSOM custom-property case handling, `var()` fallback token-stream minification, SourceMap, bundle/import graph, CSS Modules, media-query, or target-prefixing slices.
- The slice is limited to native substitution parity and cycle preservation for custom property variable references.

Dependency closure:
- No new support component is needed. This reuses the native `CssMinifier` scanner, raw function reader, top-level delimiter splitting, math folding, and declaration-value minification helpers.

Root harness:
- Not run; isolated micro-slice.
