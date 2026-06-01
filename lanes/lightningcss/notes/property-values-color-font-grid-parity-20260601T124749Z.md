# Property Values Color/Font/Grid Parity - 2026-06-01T12:47Z

## Source Truth

- Lane: `lightningcss`
- Micro-slice: `lightningcss-property-values-color-font-grid-parity-20260601T124749Z`
- Base accepted HEAD: `704eae59a88752ecf27635aa23232c135e0688b2`
- Upstream: `parcel-bundler/lightningcss` pinned commit `22bdda3d190f1cd321d98026225cfc964af64ad9`
- Targeted upstream source: pristine `git show 22bdda3d190f1cd321d98026225cfc964af64ad9:src/lib.rs`, `test_color` relative `hsl(from ...)` and `hwb(from ...)` tail rows.

## Behavior

This slice deepens the represented property-value cluster with 28 additional upstream HSL/HWB relative-color rows:

- rgb-origin zero-angle replacements for HSL/HWB hue channels.
- rgb-origin constant hue, alpha override, and permutation rows.
- `calc(alpha * 100)` and `calc(... / 100)` channel reuse rows.
- source alpha `none` rows that serialize to transparent shorthand colors.

The native PHP minifier already evaluates these rows to the upstream shortest color output, so the implementation change is focused test coverage plus a WordPress smoke extension. The WordPress smoke now guards relative HSL/HWB minification for block `caret-color` and `column-rule-color` tokens.

## Evidence

- `php tools/run-tests.php lanes/lightningcss/tests/CssMinifierTest.php`
  - `1 test files, 2004 assertions, 0 failures`
- `php tools/run-tests.php lanes/lightningcss/tests`
  - `13 test files, 7871 assertions, 0 failures`
- `php -l lanes/lightningcss/tests/CssMinifierTest.php`
  - `No syntax errors detected`
- `php -l lanes/lightningcss/examples/wordpress-color-value-minifier.php`
  - `No syntax errors detected`
- `php lanes/lightningcss/examples/wordpress-color-value-minifier.php >/tmp/lightningcss-wordpress-color-value-minifier.out && wc -c /tmp/lightningcss-wordpress-color-value-minifier.out`
  - `2767 /tmp/lightningcss-wordpress-color-value-minifier.out`
- `php -r 'json_decode(file_get_contents("lanes/lightningcss/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "json ok\n";'`
  - `json ok`
- `git diff --check -- lanes/lightningcss`
  - passed with no output

## Status Delta

- `phpPass`: `7843 -> 7871`
- `phpFail`: `0`
- Conservative mapped coverage remains `2392 / 3532`; this slice is counted as a represented-cluster deepening rather than a denominator increase.

## Non-Overlap

This does not repeat the accepted custom-property var fallback token-stream minification slice or the earlier LCH/display-p3 color-mix parity slice. It stays inside the upstream `test_color` relative HSL/HWB tail rows that were not directly guarded by the current accepted `CssMinifierTest.php`.

## Dependency Closure

No new support component is needed. The existing native PHP `CssMinifier` color evaluator and WordPress color-value example path cover the behavior without Node, WASM, Rust runners, or external services.
